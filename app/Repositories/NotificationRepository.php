<?php

namespace App\Repositories;

use App\Models\Notification as NotificationModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NotificationRepository extends BaseRepository
{
    public function __construct(NotificationModel $model)
    {
        parent::__construct($model);
    }

    /**
     * Get notifications for user
     */
    public function forUser(int $userId): Builder
    {
        return $this->model->where('user_id', $userId);
    }

    /**
     * Get unread notifications
     */
    public function unread(): Builder
    {
        return $this->model->where('is_read', false);
    }

    /**
     * Get read notifications
     */
    public function read(): Builder
    {
        return $this->model->where('is_read', true);
    }

    /**
     * Get notifications by type
     */
    public function byType(string $type): Builder
    {
        return $this->model->where('type', $type);
    }

    /**
     * Get notifications for ticket
     */
    public function forTicket(int $ticketId): Builder
    {
        return $this->model->where('ticket_id', $ticketId);
    }

    /**
     * Get paginated notifications for user
     */
    public function paginateForUser(
        User $user,
        int $perPage = 20,
        ?string $type = null,
        ?bool $isRead = null
    ): LengthAwarePaginator {
        $query = $this->model->with('ticket')
            ->where('user_id', $user->id)
            ->latest();

        if ($type) {
            $query->where('type', $type);
        }

        if ($isRead !== null) {
            $query->where('is_read', $isRead);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->model->where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId): bool
    {
        $notification = $this->findOrFail($notificationId);
        $notification->is_read = true;
        $notification->read_at = now();
        return $notification->save();
    }

    /**
     * Mark all as read for user
     */
    public function markAllAsReadForUser(int $userId): int
    {
        return $this->model->where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Create notification
     */
    public function create(array $data): NotificationModel
    {
        return $this->model->create([
            'user_id' => $data['user_id'],
            'ticket_id' => $data['ticket_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'data' => $data['data'] ?? null,
        ]);
    }

    /**
     * Notify CSKH users about new ticket
     */
    public function notifyCsKHAboutTicket(int $ticketId, string $ticketNumber, string $subject): void
    {
        $cskhUsers = \App\Models\User::role(['Admin', 'CSKH'])->get();

        foreach ($cskhUsers as $user) {
            $this->create([
                'user_id' => $user->id,
                'ticket_id' => $ticketId,
                'type' => NotificationModel::TYPE_NEW_TICKET,
                'title' => 'New Ticket Created',
                'message' => "Ticket {$ticketNumber}: {$subject}",
            ]);
        }
    }

    /**
     * Notify user about ticket update
     */
    public function notifyUserAboutTicketUpdate(int $userId, int $ticketId, string $status): void
    {
        $this->create([
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'type' => NotificationModel::TYPE_TICKET_UPDATED,
            'title' => 'Ticket Updated',
            'message' => "Your ticket status changed to {$status}",
        ]);
    }

    /**
     * Notify user about new message
     */
    public function notifyUserAboutMessage(int $userId, int $ticketId, string $message): void
    {
        $this->create([
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'type' => NotificationModel::TYPE_NEW_MESSAGE,
            'title' => 'New Message',
            'message' => substr($message, 0, 100),
        ]);
    }

    /**
     * Notify CSKH about ticket assignment
     */
    public function notifyCsKHAboutAssignment(int $userId, int $ticketId, string $ticketNumber): void
    {
        $this->create([
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'type' => NotificationModel::TYPE_TICKET_ASSIGNED,
            'title' => 'New Ticket Assigned',
            'message' => "Ticket {$ticketNumber} has been assigned to you",
        ]);
    }

    /**
     * Delete old notifications (keep last N days)
     */
    public function deleteOld(int $days = 90): int
    {
        return $this->model->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Clean read notifications older than N days
     */
    public function cleanOldRead(int $days = 30): int
    {
        return $this->model->where('is_read', true)
            ->where('read_at', '<', now()->subDays($days))
            ->delete();
    }
}
