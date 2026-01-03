<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Models\TicketEscalation;
use App\Models\User;
use App\Notifications\TicketEscalatedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job kiểm tra và xử lý escalation cho ticket
 *
 * - Check xem ticket có cần cảnh báo warning (Telegram) chưa
 * - Check xem ticket có cần escalate lên Admin chưa
 */
class CheckTicketEscalation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Ticket instance
     */
    public Ticket $ticket;

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
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        // Reload ticket to get latest data
        $this->ticket->refresh();

        // Check Warning Level (Telegram notification)
        if ($this->ticket->shouldEscalateWarning()) {
            $this->sendWarningNotification();
        }

        // Check Escalate to Admin Level
        if ($this->ticket->shouldEscalateToAdmin()) {
            $this->sendAdminEscalation();
        }
    }

    /**
     * Gửi cảnh báo warning qua Telegram
     */
    protected function sendWarningNotification(): void
    {
        try {
            // Create escalation record
            TicketEscalation::create([
                'ticket_id' => $this->ticket->id,
                'escalation_level' => 'warning',
                'escalated_at' => now(),
                'notification_type' => 'telegram',
                'is_resolved' => false,
            ]);

            // Update ticket first_escalated_at if not set
            if (!$this->ticket->first_escalated_at) {
                $this->ticket->update(['first_escalated_at' => now()]);
            }

            // Send Telegram notification
            dispatch(new SendTelegramNotification(
                $this->ticket,
                'sla_warning',
                [
                    'response_time' => $this->ticket->getResponseTimeMinutes(),
                    'minutes_elapsed' => $this->ticket->getMinutesSinceResponseStart(),
                ]
            ));

            Log::info("SLA Warning sent for ticket {$this->ticket->ticket_number}");
        } catch (\Exception $e) {
            Log::error("Failed to send SLA warning for ticket {$this->ticket->ticket_number}: " . $e->getMessage());
        }
    }

    /**
     * Escalate lên Admin
     */
    protected function sendAdminEscalation(): void
    {
        try {
            // Create escalation record
            TicketEscalation::create([
                'ticket_id' => $this->ticket->id,
                'escalation_level' => 'escalated',
                'escalated_at' => now(),
                'notification_type' => 'admin',
                'is_resolved' => false,
            ]);

            // Update ticket is_escalated flag
            $this->ticket->update(['is_escalated' => true]);

            // Send Telegram notification for escalation
            dispatch(new SendTelegramNotification(
                $this->ticket,
                'sla_escalated',
                [
                    'escalation_threshold' => $this->ticket->getEscalationThresholdMinutes(),
                    'minutes_elapsed' => $this->ticket->getMinutesSinceResponseStart(),
                ]
            ));

            // Notify all Admin users via database notification
            $admins = User::role('Admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new TicketEscalatedNotification($this->ticket));
            }

            Log::info("SLA Escalation sent to Admin for ticket {$this->ticket->ticket_number}");
        } catch (\Exception $e) {
            Log::error("Failed to escalate ticket {$this->ticket->ticket_number}: " . $e->getMessage());
        }
    }

    /**
     * Get the tags that should be assigned to the job
     */
    public function tags(): array
    {
        return ['sla', 'escalation', 'ticket:' . $this->ticket->id];
    }
}
