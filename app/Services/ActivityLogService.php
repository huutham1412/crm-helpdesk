<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ActivityLogDetail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    protected ?int $userId = null;
    protected ?int $impersonatedBy = null;
    protected ?string $ipAddress = null;
    protected ?string $userAgent = null;

    public function __construct()
    {
        $this->ipAddress = Request::ip();
        $this->userAgent = Request::userAgent();
    }

    public function setUserId(?int $userId, ?int $impersonatedBy = null): self
    {
        $this->userId = $userId;
        $this->impersonatedBy = $impersonatedBy;
        return $this;
    }

    public function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        array $changes = [],
        string $logLevel = 'info',
        array $tags = [],
        array $properties = []
    ): ?ActivityLog {
        if (!$this->shouldLog($action)) {
            return null;
        }

        $data = [
            'user_id' => $this->userId ?? auth()->id(),
            'impersonated_by' => $this->impersonatedBy,
            'action' => $action,
            'log_level' => $logLevel,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'description' => $description,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'tags' => $tags,
            'properties' => $properties,
        ];

        $activityLog = ActivityLog::create($data);

        if (!empty($changes) && $activityLog) {
            $this->logChanges($activityLog, $changes);
        }

        return $activityLog;
    }

    protected function logChanges(ActivityLog $log, array $changes): void
    {
        $details = collect($changes)->map(fn($change, $field) => [
            'activity_log_id' => $log->id,
            'field_name' => $field,
            'old_value' => $change['old'] ?? null,
            'new_value' => $change['new'] ?? null,
        ])->toArray();

        foreach ($details as $detail) {
            ActivityLogDetail::create($detail);
        }
    }

    protected function shouldLog(string $action): bool
    {
        if (!config('activity-log.enabled', true)) {
            return false;
        }

        $ignoredActions = config('activity-log.ignored_actions', []);
        $onlyActions = config('activity-log.only_actions', []);

        if (in_array($action, $ignoredActions)) {
            return false;
        }

        if (!empty($onlyActions) && !in_array($action, $onlyActions)) {
            return false;
        }

        return true;
    }

    public function logLogin(?int $userId, bool $successful = true): ?ActivityLog
    {
        return $this->log(
            $successful ? 'login' : 'login_failed',
            null,
            $successful ? 'User logged in' : 'Failed login attempt',
            [],
            $successful ? 'info' : 'warning',
            ['authentication']
        );
    }

    public function logLogout(?int $userId): ?ActivityLog
    {
        return $this->log(
            'logout',
            null,
            'User logged out',
            [],
            'info',
            ['authentication']
        );
    }

    public function logPasswordChanged(Model $user): ?ActivityLog
    {
        return $this->log(
            'password_changed',
            $user,
            "Password changed for user: {$user->email}",
            [],
            'info',
            ['authentication', 'security']
        );
    }

    public function logCreated(Model $model, ?string $description = null): ?ActivityLog
    {
        $modelName = class_basename($model);
        $action = strtolower($modelName) . '.created';

        return $this->log(
            $action,
            $model,
            $description ?? "{$modelName} created",
            [],
            'info',
            [strtolower($modelName)]
        );
    }

    public function logUpdated(Model $model, array $changes = [], ?string $description = null): ?ActivityLog
    {
        $modelName = class_basename($model);
        $action = strtolower($modelName) . '.updated';

        return $this->log(
            $action,
            $model,
            $description ?? "{$modelName} updated",
            $changes,
            'info',
            [strtolower($modelName)]
        );
    }

    public function logDeleted(Model $model, ?string $description = null): ?ActivityLog
    {
        $modelName = class_basename($model);
        $action = strtolower($modelName) . '.deleted';

        return $this->log(
            $action,
            $model,
            $description ?? "{$modelName} deleted",
            [],
            'warning',
            [strtolower($modelName)]
        );
    }

    public function cleanOldLogs(): int
    {
        $retentionDays = config('activity-log.retention_days', 90);
        $cutoffDate = now()->subDays($retentionDays);

        return ActivityLog::where('created_at', '<', $cutoffDate)->delete();
    }
}
