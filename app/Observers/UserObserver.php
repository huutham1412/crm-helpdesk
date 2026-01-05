<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ActivityLogService;

class UserObserver
{
    public function __construct(protected ActivityLogService $logger)
    {
    }

    public function created(User $user): void
    {
        $this->logger->log(
            'user.created',
            $user,
            "Tạo người dùng: {$user->name} ({$user->email})",
            [],
            'info',
            ['user']
        );
    }

    public function updated(User $user): void
    {
        if (!$this->shouldLogUpdate($user)) {
            return;
        }

        $changes = $this->getTrackedChanges($user);

        if (!empty($changes)) {
            $this->logger->log(
                'user.updated',
                $user,
                "Cập nhật người dùng: {$user->name}",
                $changes,
                'info',
                ['user']
            );
        }

        // Check for role changes
        if ($user->isDirty('roles')) {
            $this->logger->log(
                'user.roles_changed',
                $user,
                "Thay đổi vai trò: {$user->name}",
                [],
                'warning',
                ['user', 'role']
            );
        }
    }

    public function deleted(User $user): void
    {
        $this->logger->log(
            'user.deleted',
            $user,
            "Xóa người dùng: {$user->name} ({$user->email})",
            [],
            'warning',
            ['user']
        );
    }

    protected function shouldLogUpdate(User $user): bool
    {
        $dirty = $user->getDirty();
        unset($dirty['updated_at'], $dirty['email_verified_at']);
        return !empty($dirty);
    }

    protected function getTrackedChanges(User $user): array
    {
        $changes = [];
        $trackedFields = ['name', 'email', 'phone', 'is_active'];

        foreach ($trackedFields as $field) {
            if ($user->isDirty($field)) {
                $old = $user->getOriginal($field);
                $new = $user->$field;

                if ($field === 'email' && $old) {
                    $old = $this->maskEmail($old);
                }

                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';

        if (strlen($name) <= 2) {
            return str_repeat('*', strlen($name)) . '@' . $domain;
        }

        return $name[0] . str_repeat('*', strlen($name) - 2) . $name[strlen($name) - 1] . '@' . $domain;
    }
}
