<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\PasswordReset;
use App\Listeners\LogAuthentication;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [
            LogAuthentication::class,
        ],
        Logout::class => [
            LogAuthentication::class,
        ],
        Failed::class => [
            LogAuthentication::class,
        ],
        PasswordReset::class => [
            LogAuthentication::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
