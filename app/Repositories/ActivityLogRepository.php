<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ActivityLogRepository
{
    public function __construct(protected ActivityLog $model)
    {
    }

    public function paginate(int $perPage = 50, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['user:id,name,email', 'impersonatedBy:id,name']);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['actions'])) {
            $query->whereIn('action', $filters['actions']);
        }

        if (!empty($filters['subject_type']) && !empty($filters['subject_id'])) {
            $query->where('subject_type', $filters['subject_type'])
                  ->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['log_level'])) {
            $query->where('log_level', $filters['log_level']);
        }

        if (!empty($filters['tags'])) {
            $query->whereJsonContains('tags', $filters['tags']);
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
        } elseif (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        } elseif (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function withDetails(int $id): ?ActivityLog
    {
        return $this->model->with(['user', 'impersonatedBy', 'details'])
            ->find($id);
    }

    public function getSubjectHistory(string $subjectType, int $subjectId): Collection
    {
        return $this->model->with(['user:id,name', 'details'])
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->latest()
            ->get();
    }

    public function getStatistics(?string $from = null, ?string $to = null): array
    {
        $query = $this->model->newQuery();

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        $total = (clone $query)->count();

        $byAction = (clone $query)->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        $byLevel = (clone $query)->selectRaw('log_level, COUNT(*) as count')
            ->groupBy('log_level')
            ->pluck('count', 'log_level')
            ->toArray();

        $byUser = (clone $query)->selectRaw('user_id, COUNT(*) as count')
            ->with('user:id,name')
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn($item) => [
                'user' => $item->user?->name ?? 'System',
                'count' => $item->count,
            ]);

        return [
            'total' => $total,
            'by_action' => $byAction,
            'by_level' => $byLevel,
            'by_user' => $byUser,
        ];
    }

    public function export(array $filters = []): BinaryFileResponse
    {
        $query = $this->model->with(['user', 'subject']);

        foreach ($filters as $key => $value) {
            if ($value !== null) {
                if ($key === 'date_from') {
                    $query->whereDate('created_at', '>=', $value);
                } elseif ($key === 'date_to') {
                    $query->whereDate('created_at', '<=', $value);
                } elseif ($key === 'action') {
                    $query->where('action', $value);
                } elseif ($key === 'user_id') {
                    $query->where('user_id', $value);
                }
            }
        }

        $logs = $query->latest()
            ->limit(config('activity-log.export.max_export_rows', 10000))
            ->get();

        return $this->exportCsv($logs);
    }

    protected function exportCsv(Collection $logs): BinaryFileResponse
    {
        $filename = "activity_logs_" . now()->format('Y_m_d_His') . ".csv";
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['Ngày', 'Người dùng', 'Hành động', 'Mô tả', 'IP Address']);

        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->created_at?->format('Y-m-d H:i:s'),
                $log->user?->name ?? 'System',
                $log->action,
                $log->description,
                $log->ip_address,
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response()->streamDownload(
            fn() => print($content),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }
}
