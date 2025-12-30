<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\SendTelegramNotification;
use App\Events\TicketUpdated;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Ticket;
use App\Repositories\TicketRepository;
use App\Repositories\NotificationRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    protected TicketRepository $ticketRepo;
    protected NotificationRepository $notificationRepo;

    public function __construct(TicketRepository $ticketRepo, NotificationRepository $notificationRepo)
    {
        $this->ticketRepo = $ticketRepo;
        $this->notificationRepo = $notificationRepo;
    }

    /**
     * List tickets with filters
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->status,
            'priority' => $request->priority,
            'category_id' => $request->category_id,
            'search' => $request->search,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
        ];

        $tickets = $this->ticketRepo->paginateForUser($request->user(), 20, $filters);

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * Create new ticket
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'nullable|exists:categories,id',
            'priority' => 'in:low,medium,high,urgent',
            'initial_message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ticket = $this->ticketRepo->create([
            'user_id' => $request->user()->id,
            'category_id' => $request->category_id,
            'priority' => $request->priority ?? 'medium',
            'status' => 'open',
            'subject' => $request->subject,
            'description' => $request->description,
        ]);

        // Create initial message if provided
        if ($request->filled('initial_message')) {
            Message::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'message' => $request->initial_message,
                'message_type' => 'text',
            ]);
        }

        // Notify CSKH users
        $this->notificationRepo->notifyCsKHAboutTicket(
            $ticket->id,
            $ticket->ticket_number,
            $ticket->subject
        );

        // Gửi thông báo Telegram (async qua queue)
        $notificationType = $ticket->priority === 'urgent' ? 'urgent_ticket' : 'new_ticket';
        SendTelegramNotification::dispatch($ticket, $notificationType);

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully',
            'data' => [
                'ticket' => $ticket->load('user', 'category'),
            ],
        ], 201);
    }

    /**
     * Get single ticket
     */
    public function show(Request $request, $id): JsonResponse
    {
        $ticket = $this->ticketRepo->withMessages($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        // Authorization check
        if (!$request->user()->isCsKH() && $ticket->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ticket' => $ticket,
            ],
        ]);
    }

    /**
     * Update ticket (CSKH/Admin only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $ticket = $this->ticketRepo->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'in:open,processing,pending,resolved,closed',
            'priority' => 'in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'category_id' => 'nullable|exists:categories,id',
            'resolution' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldStatus = $ticket->status;

        // Handle status-specific logic
        if ($request->has('status')) {
            $ticket = $this->ticketRepo->updateStatus($id, $request->status);
        }

        // Update other fields
        $ticket->update($request->only(['priority', 'assigned_to', 'category_id', 'resolution']));

        // Create system message for status change
        if ($request->has('status') && $request->status !== $oldStatus) {
            Message::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'message' => "Status changed from {$oldStatus} to {$ticket->status}",
                'message_type' => 'system',
            ]);

            // Notify ticket user
            if ($ticket->user_id !== $request->user()->id) {
                $this->notificationRepo->notifyUserAboutTicketUpdate(
                    $ticket->user_id,
                    $ticket->id,
                    $ticket->status
                );
            }

            // Gửi thông báo Telegram khi status thay đổi
            SendTelegramNotification::dispatch($ticket, 'status_changed', [
                'old_status' => $oldStatus,
                'new_status' => $ticket->status,
            ]);

            // Broadcast realtime event for ticket update
            try {
                broadcast(new TicketUpdated($ticket));
            } catch (\Exception $e) {
                Log::error('Failed to broadcast TicketUpdated event: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated successfully',
            'data' => [
                'ticket' => $ticket->load('user', 'assignedTo', 'category'),
            ],
        ]);
    }

    /**
     * Assign ticket to CSKH
     */
    public function assign(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ticket = $this->ticketRepo->assignTo($id, $request->user_id);

        // Create system message
        Message::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => "Ticket assigned to " . $ticket->assignedTo->name,
            'message_type' => 'system',
        ]);

        // Notify assigned CSKH
        if ($ticket->assigned_to !== $request->user()->id) {
            $this->notificationRepo->notifyCsKHAboutAssignment(
                $ticket->assigned_to,
                $ticket->id,
                $ticket->ticket_number
            );
        }

        // Gửi thông báo Telegram khi ticket được assign
        SendTelegramNotification::dispatch($ticket, 'ticket_assigned');

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned successfully',
            'data' => [
                'ticket' => $ticket->load('assignedTo'),
            ],
        ]);
    }

    /**
     * Delete ticket (Admin only)
     */
    public function destroy($id): JsonResponse
    {
        $deleted = $this->ticketRepo->delete($id);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket deleted successfully',
        ]);
    }
}
