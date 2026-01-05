<?php

namespace App\Listeners;

use App\Services\ActivityLogService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;

class LogAuthentication
{
    public function __construct(protected ActivityLogService $logger)
    {
    }

    public function login(Login $event): void
    {
        $this->logger->setUserId($event->user->id)->log(
            'login',
            null,
            "Người dùng {$event->user->name} đăng nhập",
            [],
            'info',
            ['authentication']
        );
    }

    public function logout(Logout $event): void
    {
        $this->logger->setUserId($event->user->id)->log(
            'logout',
            null,
            "Người dùng {$event->user->name} đăng xuất",
            [],
            'info',
            ['authentication']
        );
    }

    public function failed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? 'unknown';

        $this->logger->log(
            'login_failed',
            null,
            "Đăng nhập thất bại cho: {$email}",
            [],
            'warning',
            ['authentication', 'security']
        );
    }

    public function passwordReset(PasswordReset $event): void
    {
        $this->logger->logPasswordChanged($event->user);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'login',
            Logout::class => 'logout',
            Failed::class => 'failed',
            PasswordReset::class => 'passwordReset',
        ];
    }
}
