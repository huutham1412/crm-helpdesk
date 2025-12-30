<?php

namespace App\Repositories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MessageRepository extends BaseRepository
{
    public function __construct(Message $model)
    {
        parent::__construct($model);
    }

    /**
     * Get messages for a ticket
     */
    public function forTicket(int $ticketId): Builder
    {
        return $this->model->where('ticket_id', $ticketId);
    }

    /**
     * Get public messages (non-internal)
     */
    public function publicMessages(): Builder
    {
        return $this->model->where('is_internal', false);
    }

    /**
     * Get internal messages
     */
    public function internalMessages(): Builder
    {
        return $this->model->where('is_internal', true);
    }

    /**
     * Get messages by type
     */
    public function byType(string $type): Builder
    {
        return $this->model->where('message_type', $type);
    }

    /**
     * Get system messages
     */
    public function systemMessages(): Builder
    {
        return $this->model->where('message_type', 'system');
    }

    /**
     * Get paginated messages for ticket
     */
    public function paginateForTicket(int $ticketId, bool $includeInternal = false, int $perPage = 50): LengthAwarePaginator
    {
        $query = $this->model->with(['user', 'attachmentObjects'])
            ->where('ticket_id', $ticketId);

        if (!$includeInternal) {
            $query->where('is_internal', false);
        }

        return $query->oldest()->paginate($perPage);
    }

    /**
     * Get ticket messages with user and ticket relationships
     */
    public function getTicketMessages(int $ticketId, bool $includeInternal = false): Collection
    {
        $query = $this->model->with('user')
            ->where('ticket_id', $ticketId);

        if (!$includeInternal) {
            $query->where('is_internal', false);
        }

        return $query->oldest()->get();
    }

    /**
     * Create message for ticket
     */
    public function createForTicket(int $ticketId, array $data): Message
    {
        return $this->model->create([
            'ticket_id' => $ticketId,
            'user_id' => $data['user_id'],
            'message' => $data['message'],
            'message_type' => $data['message_type'] ?? 'text',
            'attachments' => $data['attachments'] ?? null,
            'is_internal' => $data['is_internal'] ?? false,
        ]);
    }

    /**
     * Get latest message for ticket
     */
    public function latestForTicket(int $ticketId): ?Message
    {
        return $this->model->where('ticket_id', $ticketId)
            ->latest()
            ->first();
    }

    /**
     * Count messages for ticket
     */
    public function countForTicket(int $ticketId): int
    {
        return $this->model->where('ticket_id', $ticketId)->count();
    }

    /**
     * Delete messages for ticket
     */
    public function deleteForTicket(int $ticketId): int
    {
        return $this->model->where('ticket_id', $ticketId)->delete();
    }

    /**
     * Get recent messages
     */
    public function recent(int $limit = 10): Collection
    {
        return $this->model->with(['ticket', 'user'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
