<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\SendTelegramNotification;
use App\Events\NewMessage;
use App\Events\MessageRead;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Notification;
use App\Models\Message;
use App\Models\Ticket;
use App\Repositories\MessageRepository;
use App\Repositories\NotificationRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    protected MessageRepository $messageRepo;
    protected NotificationRepository $notificationRepo;

    public function __construct(MessageRepository $messageRepo, NotificationRepository $notificationRepo)
    {
        $this->messageRepo = $messageRepo;
        $this->notificationRepo = $notificationRepo;
    }

    /**
     * List messages for a ticket
     */
    public function index(Request $request, $ticketId): JsonResponse
    {
        $user = $request->user();
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        // Authorization check
        if (!$user->isCsKH() && $ticket->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Regular users don't see internal messages
        $includeInternal = $user->isCsKH();
        $messages = $this->messageRepo->paginateForTicket($ticketId, $includeInternal, 50);

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    /**
     * Send message to a ticket
     */
    public function store(Request $request, $ticketId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string',
            'attachment_ids' => 'nullable|array',
            'attachment_ids.*' => 'exists:attachments,id',
            'attachments' => 'nullable|array',
            'is_internal' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Require either message text or attachments
        if (empty($request->message) && empty($request->attachment_ids) && empty($request->attachments)) {
            return response()->json([
                'success' => false,
                'message' => 'Either message text or attachments are required',
            ], 422);
        }

        $user = $request->user();
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        // Authorization check
        if (!$user->isCsKH() && $ticket->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Only CSKH can send internal messages
        $isInternal = $request->get('is_internal', false);
        if ($isInternal && !$user->isCsKH()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - only CSKH can send internal messages',
            ], 403);
        }

        $message = $this->messageRepo->createForTicket($ticketId, [
            'user_id' => $user->id,
            'message' => $request->message ?? '',
            'message_type' => 'text',
            'attachments' => $request->attachments,
            'is_internal' => $isInternal,
        ]);

        // Link attachments to message
        $attachmentIds = $request->input('attachment_ids', []);
        if (!empty($attachmentIds)) {
            Attachment::whereIn('id', $attachmentIds)
                ->where('ticket_id', $ticketId)
                ->whereNull('message_id')
                ->update(['message_id' => $message->id]);
        }

        // Update ticket status if it was closed
        if ($ticket->status === 'closed') {
            $ticket->update(['status' => 'open']);
        }

        // Notify the other party (if not internal)
        if (!$isInternal) {
            $recipientId = $user->id === $ticket->user_id
                ? ($ticket->assigned_to ?? null)
                : $ticket->user_id;

            if ($recipientId && $recipientId !== $user->id) {
                $this->notificationRepo->notifyUserAboutMessage(
                    $recipientId,
                    $ticketId,
                    $request->message ?? 'Đã gửi file đính kèm'
                );
            }

            // Gửi thông báo Telegram khi có tin nhắn mới từ user (chỉ notify khi user gửi, không spam khi CSKH trả lời)
            if ($user->id === $ticket->user_id) {
                SendTelegramNotification::dispatch($ticket->load('user'), 'new_message', [
                    'message' => $request->message ?? 'Đã gửi file đính kèm',
                    'sender_name' => $user->name,
                ]);
            }

            // Broadcast realtime event for new message (to others only)
            try {
                broadcast(new NewMessage($message, $ticket))->toOthers();
            } catch (\Exception $e) {
                Log::error('Failed to broadcast NewMessage event: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => [
                'message' => $message->load(['user', 'attachmentObjects']),
            ],
        ], 201);
    }

    /**
     * Broadcast typing indicator
     */
    public function typing(Request $request, $ticketId): JsonResponse
    {
        $user = $request->user();
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        // Authorization check
        if (!$user->isCsKH() && $ticket->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Broadcast typing event
        try {
            broadcast(new \App\Events\UserTyping($ticketId, $user->id, $user->name, true));
        } catch (\Exception $e) {
            Log::error('Failed to broadcast UserTyping event: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Mark messages as read for a ticket
     */
    public function markAsRead(Request $request, $ticketId): JsonResponse
    {
        $user = $request->user();
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        // Authorization check
        if (!$user->isCsKH() && $ticket->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Get the message ID if specific message, otherwise mark all unread as read
        $messageId = $request->input('message_id');

        if ($messageId) {
            // Mark specific message as read
            $message = Message::where('ticket_id', $ticketId)
                ->where('id', $messageId)
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found',
                ], 404);
            }

            // Only mark as read if not the sender
            if ($message->user_id !== $user->id) {
                if (!$message->read_at) {
                    $message->markAsRead();

                    // Broadcast that message was read (with reader's user_id)
                    try {
                        broadcast(new MessageRead($message, $user->id));
                    } catch (\Exception $e) {
                        Log::error('Failed to broadcast MessageRead event: ' . $e->getMessage());
                    }
                }
            }
        } else {
            // Mark all unread messages (not sent by current user) as read
            $unreadMessages = Message::where('ticket_id', $ticketId)
                ->where('user_id', '!=', $user->id)
                ->whereNull('read_at')
                ->get();

            foreach ($unreadMessages as $msg) {
                $msg->markAsRead();

                // Broadcast each message read event (with reader's user_id)
                try {
                    broadcast(new MessageRead($msg, $user->id));
                } catch (\Exception $e) {
                    Log::error('Failed to broadcast MessageRead event: ' . $e->getMessage());
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read',
        ]);
    }
}
