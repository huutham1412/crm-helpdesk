<?php

namespace App\Jobs;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

/**
 * Job gửi thông báo Telegram khi có ticket mới hoặc cập nhật ticket
 *
 * Usage:
 * dispatch(new SendTelegramNotification($ticket, 'new_ticket'));
 * dispatch(new SendTelegramNotification($ticket, 'status_changed', ['old_status' => 'open', 'new_status' => 'processing']));
 */
class SendTelegramNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Ticket instance
     */
    protected Ticket $ticket;

    /**
     * Loại notification: new_ticket, status_changed, ticket_assigned, new_message, sla_warning, sla_escalated
     */
    protected string $type;

    /**
     * Dữ liệu bổ sung cho notification
     */
    protected array $data;

    /**
     * Số lần retry tối đa
     */
    public int $tries = 3;

    /**
     * Timeout cho job (giây)
     */
    public int $timeout = 30;

    /**
     * Tạo job instance mới
     */
    public function __construct(Ticket $ticket, string $type = 'new_ticket', array $data = [])
    {
        $this->ticket = $ticket;
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        $chatId = config('services.telegram.chat_id');
        $botToken = config('services.telegram.bot_token');

        // Kiểm tra cấu hình
        if (empty($chatId) || empty($botToken)) {
            Log::warning('Telegram notification skipped: Missing chat_id or bot_token');
            return;
        }

        try {
            $telegram = new Api($botToken);
            $message = $this->buildMessage();

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);

            Log::info("Telegram notification sent: {$this->type} for ticket {$this->ticket->ticket_number}");
        } catch (\Exception $e) {
            Log::error("Failed to send Telegram notification: " . $e->getMessage());

            // Throw exception để queue sẽ retry
            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

    /**
     * Xây dựng nội dung message Telegram
     */
    protected function buildMessage(): string
    {
        $ticket = $this->ticket->load(['user', 'category', 'assignedTo']);

        switch ($this->type) {
            case 'new_ticket':
                return $this->buildNewTicketMessage($ticket);
            case 'status_changed':
                return $this->buildStatusChangedMessage($ticket);
            case 'ticket_assigned':
                return $this->buildAssignedMessage($ticket);
            case 'new_message':
                return $this->buildNewMessageMessage($ticket);
            case 'urgent_ticket':
                return $this->buildUrgentTicketMessage($ticket);
            case 'sla_warning':
                return $this->buildSlaWarningMessage($ticket);
            case 'sla_escalated':
                return $this->buildSlaEscalatedMessage($ticket);
            default:
                return $this->buildDefaultMessage($ticket);
        }
    }

    /**
     * Message cho ticket mới
     */
    protected function buildNewTicketMessage(Ticket $ticket): string
    {
        $priorityEmoji = $this->getPriorityEmoji($ticket->priority);
        $priorityLabel = $this->getPriorityLabel($ticket->priority);
        $categoryName = $ticket->category ? $ticket->category->name : 'Chưa phân loại';
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $ticketUrl = $frontendUrl . '/tickets/' . $ticket->id;

        return "🔔 *TICKET MỚI* {$priorityEmoji}

*Ticket Number:* `{$ticket->ticket_number}`
*Tiêu đề:* {$ticket->subject}
*Người tạo:* {$ticket->user->name}
*Email:* {$ticket->user->email}
*Độ ưu tiên:* {$priorityLabel}
*Danh mục:* {$categoryName}

*Mô tả:*
```" . substr($ticket->description, 0, 200) . (strlen($ticket->description) > 200 ? '...' : '') . "```

🔗 [Xem ticket]({$ticketUrl})
⏰ " . $ticket->created_at->format('H:i d/m/Y');
    }

    /**
     * Message khi status thay đổi
     */
    protected function buildStatusChangedMessage(Ticket $ticket): string
    {
        $oldStatus = $this->data['old_status'] ?? '';
        $newStatus = $this->data['new_status'] ?? $ticket->status;

        return "🔄 *THAY ĐỔI TRẠNG THÁI*

*Ticket:* `{$ticket->ticket_number}`
*Tiêu đề:* {$ticket->subject}

Trạng thái đã thay đổi:
❌ *{$this->getStatusLabel($oldStatus)}* ➔ ✅ *{$this->getStatusLabel($newStatus)}*

⏰ " . now()->format('H:i d/m/Y');
    }

    /**
     * Message khi ticket được assign
     */
    protected function buildAssignedMessage(Ticket $ticket): string
    {
        $assignedTo = $ticket->assignedTo;
        $assignedName = $assignedTo ? $assignedTo->name : 'Chưa gán';

        return "👤 *TICKET ĐÃ ĐƯỢC GÁN*

*Ticket:* `{$ticket->ticket_number}`
*Tiêu đề:* {$ticket->subject}
*Người xử lý:* {$assignedName}

⏰ " . now()->format('H:i d/m/Y');
    }

    /**
     * Message khi có tin nhắn mới
     */
    protected function buildNewMessageMessage(Ticket $ticket): string
    {
        $messageContent = $this->data['message'] ?? '';
        $senderName = $this->data['sender_name'] ?? 'Người dùng';

        return "💬 *TIN NHẮN MỚI*

*Ticket:* `{$ticket->ticket_number}`
*Tiêu đề:* {$ticket->subject}
*Người gửi:* {$senderName}

*Nội dung:*
```" . substr($messageContent, 0, 150) . (strlen($messageContent) > 150 ? '...' : '') . "```

⏰ " . now()->format('H:i d/m/Y');
    }

    /**
     * Message cho ticket khẩn cấp
     */
    protected function buildUrgentTicketMessage(Ticket $ticket): string
    {
        $userPhone = $ticket->user->phone ?? 'Chưa cung cấp';
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $ticketUrl = $frontendUrl . '/tickets/' . $ticket->id;

        return "🚨 *TICKET KHẨN CẤP*

*Ticket:* `{$ticket->ticket_number}`
*Tiêu đề:* {$ticket->subject}
*Người tạo:* {$ticket->user->name}
*Email:* {$ticket->user->email}
*SĐT:* {$userPhone}

*Mô tả:*
```" . substr($ticket->description, 0, 300) . "```

🔗 [Xem ngay]({$ticketUrl})
⏰ " . $ticket->created_at->format('H:i d/m/Y');
    }

    /**
     * Message cho SLA Warning (cảnh báo quá hạn phản hồi)
     */
    protected function buildSlaWarningMessage(Ticket $ticket): string
    {
        $priorityEmoji = $this->getPriorityEmoji($ticket->priority);
        $priorityLabel = $this->getPriorityLabel($ticket->priority);
        $responseTime = $this->data['response_time'] ?? $ticket->getResponseTimeMinutes();
        $minutesElapsed = $this->data['minutes_elapsed'] ?? $ticket->getMinutesSinceResponseStart();
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $ticketUrl = $frontendUrl . '/tickets/' . $ticket->id;

        return "⚠️ *SLA WARNING* {$priorityEmoji}

*Ticket:* `{$ticket->ticket_number}`
*Tiêu đề:* {$ticket->subject}
*Độ ưu tiên:* {$priorityLabel}
*Người tạo:* {$ticket->user->name}

Ticket chưa được phản hồi sau *{$responseTime} phút*!
🕰 Đã trôi: {$minutesElapsed} phút

🔗 [Xem ticket]({$ticketUrl})
⏰ " . now()->format('H:i d/m/Y');
    }

    /**
     * Message cho SLA Escalated (đã escalate lên Admin)
     */
    protected function buildSlaEscalatedMessage(Ticket $ticket): string
    {
        $priorityEmoji = $this->getPriorityEmoji($ticket->priority);
        $priorityLabel = $this->getPriorityLabel($ticket->priority);
        $escalationThreshold = $this->data['escalation_threshold'] ?? $ticket->getEscalationThresholdMinutes();
        $minutesElapsed = $this->data['minutes_elapsed'] ?? $ticket->getMinutesSinceResponseStart();
        $assignedTo = $ticket->assignedTo ? $ticket->assignedTo->name : 'Chưa gán';
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $ticketUrl = $frontendUrl . '/tickets/' . $ticket->id;

        return "🔴 *SLA ESCALATED* {$priorityEmoji}

*Ticket:* `{$ticket->ticket_number}`
*Tiêu đề:* {$ticket->subject}
*Độ ưu tiên:* {$priorityLabel}
*Người tạo:* {$ticket->user->name}
*Người xử lý:* {$assignedTo}

Ticket đã quá hạn *{$escalationThreshold} phút* và cần ADMIN xử lý!
🕰 Đã trôi: {$minutesElapsed} phút

🔗 [Xem ngay]({$ticketUrl})
⏰ " . now()->format('H:i d/m/Y');
    }

    /**
     * Default message
     */
    protected function buildDefaultMessage(Ticket $ticket): string
    {
        return "📢 *Thông báo Ticket*

*Ticket:* `{$ticket->ticket_number}`
*Tiêu đề:* {$ticket->subject}
*Trạng thái:* {$this->getStatusLabel($ticket->status)}

⏰ " . now()->format('H:i d/m/Y');
    }

    /**
     * Lấy emoji cho priority
     */
    protected function getPriorityEmoji(string $priority): string
    {
        $emojis = [
            'low' => '🟢',
            'medium' => '🔵',
            'high' => '🟠',
            'urgent' => '🔴',
        ];

        return $emojis[$priority] ?? '⚪';
    }

    /**
     * Lấy label tiếng Việt cho priority
     */
    protected function getPriorityLabel(string $priority): string
    {
        $labels = [
            'low' => 'Thấp',
            'medium' => 'Trung bình',
            'high' => 'Cao',
            'urgent' => 'KHẨN CẤP',
        ];

        return $labels[$priority] ?? $priority;
    }

    /**
     * Lấy label tiếng Việt cho status
     */
    protected function getStatusLabel(string $status): string
    {
        $labels = [
            'open' => 'Mới',
            'processing' => 'Đang xử lý',
            'pending' => 'Chờ xử lý',
            'resolved' => 'Đã giải quyết',
            'closed' => 'Đã đóng',
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Get the tags that should be assigned to the job
     */
    public function tags(): array
    {
        return ['telegram', 'notification', $this->type, 'ticket:' . $this->ticket->id];
    }
}
