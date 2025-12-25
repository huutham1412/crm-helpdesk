<?php

namespace App\Repositories;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TicketRepository extends BaseRepository
{
    public function __construct(Ticket $model)
    {
        parent::__construct($model);
    }

    /**
     * Get tickets for a specific user
     */
    public function forUser(int $userId): Builder
    {
        return $this->model->where('user_id', $userId);
    }

    /**
     * Get tickets assigned to CSKH
     */
    public function assignedTo(int $userId): Builder
    {
        return $this->model->where('assigned_to', $userId);
    }

    /**
     * Get open tickets
     */
    public function open(): Builder
    {
        return $this->model->whereIn('status', ['open', 'processing']);
    }

    /**
     * Get tickets by status
     */
    public function byStatus(string $status): Builder
    {
        return $this->model->where('status', $status);
    }

    /**
     * Get tickets by priority
     */
    public function byPriority(string $priority): Builder
    {
        return $this->model->where('priority', $priority);
    }

    /**
     * Get tickets by category
     */
    public function byCategory(int $categoryId): Builder
    {
        return $this->model->where('category_id', $categoryId);
    }

    /**
     * Search tickets
     */
    public function search(string $keyword): Builder
    {
        return $this->model->where(function ($query) use ($keyword) {
            $query->where('ticket_number', 'like', "%{$keyword}%")
                ->orWhere('subject', 'like', "%{$keyword}%")
                ->orWhere('description', 'like', "%{$keyword}%");
        });
    }

    /**
     * Get unassigned tickets
     */
    public function unassigned(): Builder
    {
        return $this->model->whereNull('assigned_to');
    }

    /**
     * Get paginated tickets for user based on role
     */
    public function paginateForUser(User $user, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['user', 'assignedTo', 'category']);

        // Filter based on user role
        if (!$user->isCsKH()) {
            // Regular users see only their tickets
            $query->where('user_id', $user->id);
        } elseif (!$user->isAdmin()) {
            // CSKH sees assigned tickets or unassigned
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhereNull('assigned_to');
            });
        }

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('ticket_number', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('subject', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get ticket with messages
     */
    public function withMessages(int $id): ?Ticket
    {
        return $this->model->with(['user', 'assignedTo', 'category', 'messages.user'])->find($id);
    }

    /**
     * Get statistics
     */
    public function getStatistics(?User $user = null): array
    {
        $query = $this->model->newQuery();

        if ($user && !$user->isCsKH()) {
            $query->where('user_id', $user->id);
        }

        return [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->whereIn('status', ['open', 'processing'])->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
            'closed' => (clone $query)->where('status', 'closed')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
        ];
    }

    /**
     * Get tickets by priority for stats
     */
    public function getByPriorityStats(): Collection
    {
        return $this->model->select('priority', \DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');
    }

    /**
     * Get tickets by status for stats
     */
    public function getByStatusStats(): Collection
    {
        return $this->model->select('status', \DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');
    }

    /**
     * Get tickets by category for stats
     */
    public function getByCategoryStats(): Collection
    {
        return $this->model->with('category')
            ->select('category_id', \DB::raw('count(*) as count'))
            ->groupBy('category_id')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category ? $item->category->name : 'Uncategorized',
                    'count' => $item->count,
                ];
            });
    }

    /**
     * Get tickets trend for last N days
     */
    public function getTrend(int $days = 30): Collection
    {
        return $this->model->select(
            \DB::raw('DATE(created_at) as date'),
            \DB::raw('count(*) as count')
        )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');
    }

    /**
     * Get recent tickets
     */
    public function recent(int $limit = 10): Collection
    {
        return $this->model->with(['user', 'assignedTo', 'category'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Assign ticket to user
     */
    public function assignTo(int $ticketId, int $userId): Ticket
    {
        $ticket = $this->findOrFail($ticketId);
        $ticket->update([
            'assigned_to' => $userId,
            'status' => 'processing',
        ]);
        return $ticket->fresh();
    }

    /**
     * Update ticket status
     */
    public function updateStatus(int $ticketId, string $status): Ticket
    {
        $ticket = $this->findOrFail($ticketId);
        $ticket->status = $status;

        if ($status === 'resolved') {
            $ticket->resolved_at = now();
        }

        if ($status === 'closed') {
            $ticket->closed_at = now();
        }

        $ticket->save();
        return $ticket->fresh();
    }

    /**
     * Get average resolution time (in minutes)
     */
    public function getAverageResolutionTime(): float
    {
        return $this->model->whereNotNull('resolved_at')
            ->whereNotNull('closed_at')
            ->select(\DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as avg_time'))
            ->value('avg_time') ?? 0;
    }
}
