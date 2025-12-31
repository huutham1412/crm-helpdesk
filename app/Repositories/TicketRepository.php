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
        // Date range filter
        if (!empty($filters['date_from']) && $filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to']) && $filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
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
     * Assign ticket to user (or unassign if userId is null)
     */
    public function assignTo(int $ticketId, ?int $userId): Ticket
    {
        $ticket = $this->findOrFail($ticketId);
        $ticket->update([
            'assigned_to' => $userId,
            'status' => $userId ? 'processing' : $ticket->status,
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

    /**
     * Get performance metrics for dashboard
     */
    public function getPerformanceMetrics(int $days = 30): array
    {
        $period = now()->subDays($days);

        // Tickets created per day (average)
        $avgTicketsPerDay = $this->model->where('created_at', '>=', $period)
            ->select(\DB::raw('COUNT(*) / ' . $days . ' as avg'))
            ->value('avg') ?? 0;

        // Resolution rate (resolved / total)
        $totalTickets = $this->model->where('created_at', '>=', $period)->count();
        $resolvedTickets = $this->model->where('created_at', '>=', $period)
            ->where('status', 'resolved')->count();
        $resolutionRate = $totalTickets > 0 ? ($resolvedTickets / $totalTickets) * 100 : 0;

        // Average first response time (minutes) - time to first message
        $avgFirstResponseTime = \DB::table('messages')
            ->join('tickets', 'messages.ticket_id', '=', 'tickets.id')
            ->where('messages.message_type', '!=', 'system')
            ->where('tickets.created_at', '>=', $period)
            ->where('messages.user_id', '!=', \DB::raw('tickets.user_id')) // CSKH response
            ->select(\DB::raw('AVG(TIMESTAMPDIFF(MINUTE, tickets.created_at, messages.created_at)) as avg_time'))
            ->value('avg_time') ?? 0;

        // Tickets overdue (> 24 hours without resolution for urgent/high)
        $overdueTickets = $this->model->where('status', '!=', 'closed')
            ->where('status', '!=', 'resolved')
            ->whereIn('priority', ['urgent', 'high'])
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        return [
            'avg_tickets_per_day' => round($avgTicketsPerDay, 1),
            'resolution_rate' => round($resolutionRate, 1),
            'avg_first_response_time' => round($avgFirstResponseTime, 0),
            'overdue_tickets' => $overdueTickets,
        ];
    }

    /**
     * Get CSKH performance stats
     */
    public function getCSKHPerformance(int $days = 30): Collection
    {
        $period = now()->subDays($days);

        return \DB::table('tickets as t')
            ->join('users as u', 't.assigned_to', '=', 'u.id')
            ->where('t.assigned_to', '!=', null)
            ->where('t.created_at', '>=', $period)
            ->select(
                'u.id',
                'u.name',
                \DB::raw('COUNT(*) as total_assigned'),
                \DB::raw('SUM(CASE WHEN t.status = "resolved" THEN 1 ELSE 0 END) as total_resolved'),
                \DB::raw('SUM(CASE WHEN t.status = "closed" THEN 1 ELSE 0 END) as total_closed'),
                \DB::raw('AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at) ELSE NULL END) as avg_resolution_time')
            )
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total_resolved')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'total_assigned' => $item->total_assigned,
                    'total_resolved' => $item->total_resolved,
                    'total_closed' => $item->total_closed ?? 0,
                    'avg_resolution_time' => round($item->avg_resolution_time ?? 0, 0),
                    'completion_rate' => $item->total_assigned > 0
                        ? round((($item->total_resolved + ($item->total_closed ?? 0)) / $item->total_assigned) * 100, 1)
                        : 0,
                ];
            });
    }

    /**
     * Get monthly trend comparison (current vs previous month)
     */
    public function getMonthlyTrendComparison(): array
    {
        $currentMonth = now()->month;
        $previousMonth = now()->subMonth()->month;
        $currentYear = now()->year;
        $previousYear = now()->subMonth()->year;

        $currentMonthCount = $this->model
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->count();

        $previousMonthCount = $this->model
            ->whereYear('created_at', $previousYear)
            ->whereMonth('created_at', $previousMonth)
            ->count();

        $change = $previousMonthCount > 0
            ? (($currentMonthCount - $previousMonthCount) / $previousMonthCount) * 100
            : 0;

        return [
            'current_month' => [
                'count' => $currentMonthCount,
                'label' => now()->format('F Y'),
            ],
            'previous_month' => [
                'count' => $previousMonthCount,
                'label' => now()->subMonth()->format('F Y'),
            ],
            'change_percent' => round($change, 1),
            'trend' => $change >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * Get tickets by hour (for heat map)
     */
    public function getTicketsByHour(int $days = 30): array
    {
        $data = $this->model->select(\DB::raw('HOUR(created_at) as hour'), \DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        // Fill missing hours with 0
        $result = [];
        for ($i = 0; $i < 24; $i++) {
            $result[$i] = $data[$i] ?? 0;
        }

        return $result;
    }

    /**
     * Get tickets by day of week
     */
    public function getTicketsByDayOfWeek(int $weeks = 4): array
    {
        $data = $this->model->select(\DB::raw('DAYOFWEEK(created_at) as day'), \DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subWeeks($weeks))
            ->groupBy('day')
            ->pluck('count', 'day')
            ->toArray();

        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $result = [];
        for ($i = 0; $i <= 7; $i++) {
            $result[] = [
                'day' => $dayNames[$i] ?? '',
                'count' => $data[$i] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Get SLA compliance stats
     */
    public function getSLAStats(int $days = 30): array
    {
        $period = now()->subDays($days);

        // Urgent tickets should be resolved within 4 hours
        $urgentTotal = $this->model->where('priority', 'urgent')
            ->where('created_at', '>=', $period)
            ->count();

        $urgentResolved = $this->model->where('priority', 'urgent')
            ->where('created_at', '>=', $period)
            ->whereNotNull('resolved_at')
            ->whereRaw('TIMESTAMPDIFF(MINUTE, created_at, resolved_at) <= 240') // 4 hours
            ->count();

        // High tickets should be resolved within 24 hours
        $highTotal = $this->model->where('priority', 'high')
            ->where('created_at', '>=', $period)
            ->count();

        $highResolved = $this->model->where('priority', 'high')
            ->where('created_at', '>=', $period)
            ->whereNotNull('resolved_at')
            ->whereRaw('TIMESTAMPDIFF(MINUTE, created_at, resolved_at) <= 1440') // 24 hours
            ->count();

        return [
            'urgent' => [
                'total' => $urgentTotal,
                'within_sla' => $urgentResolved,
                'sla_rate' => $urgentTotal > 0 ? round(($urgentResolved / $urgentTotal) * 100, 1) : 0,
            ],
            'high' => [
                'total' => $highTotal,
                'within_sla' => $highResolved,
                'sla_rate' => $highTotal > 0 ? round(($highResolved / $highTotal) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Get ticket status changes over time
     */
    public function getStatusFlow(int $days = 30): Collection
    {
        return $this->model->select(
            \DB::raw('DATE(created_at) as date'),
            'status',
            \DB::raw('count(*) as count')
        )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->orderBy('status')
            ->get()
            ->groupBy('date');
    }

    /**
     * Get top categories by ticket count
     */
    public function getTopCategories(int $limit = 5, int $days = 30): Collection
    {
        return \DB::table('tickets as t')
            ->join('categories as c', 't.category_id', '=', 'c.id')
            ->where('t.created_at', '>=', now()->subDays($days))
            ->select('c.name', \DB::raw('COUNT(*) as count'))
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get unassigned tickets count by priority
     */
    public function getUnassignedByPriority(): array
    {
        $data = $this->model->whereNull('assigned_to')
            ->where('status', '!=', 'closed')
            ->select('priority', \DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        return [
            'urgent' => $data['urgent'] ?? 0,
            'high' => $data['high'] ?? 0,
            'medium' => $data['medium'] ?? 0,
            'low' => $data['low'] ?? 0,
            'total' => array_sum($data),
        ];
    }
}
