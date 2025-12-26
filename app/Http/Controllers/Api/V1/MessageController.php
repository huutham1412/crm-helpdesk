<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\SendTelegramNotification;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Ticket;
use App\Repositories\MessageRepository;
use App\Repositories\NotificationRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

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
            'message' => 'required|string',
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
            'message' => $request->message,
            'message_type' => 'text',
            'attachments' => $request->attachments,
            'is_internal' => $isInternal,
        ]);

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
                    $request->message
                );
            }

            // Gửi thông báo Telegram khi có tin nhắn mới từ user (chỉ notify khi user gửi, không spam khi CSKH trả lời)
            if ($user->id === $ticket->user_id) {
                SendTelegramNotification::dispatch($ticket->load('user'), 'new_message', [
                    'message' => $request->message,
                    'sender_name' => $user->name,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => [
                'message' => $message->load('user'),
            ],
        ], 201);
    }
}
