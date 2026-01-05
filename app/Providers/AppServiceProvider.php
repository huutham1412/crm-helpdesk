<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Ticket;
use App\Models\Message;
use App\Models\Category;
use App\Models\CannedResponse;
use App\Observers\UserObserver;
use App\Observers\TicketObserver;
use App\Observers\MessageObserver;
use App\Observers\CategoryObserver;
use App\Observers\CannedResponseObserver;
use App\Services\ActivityLogService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ActivityLogService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers
        User::observe(UserObserver::class);
        Ticket::observe(TicketObserver::class);
        Message::observe(MessageObserver::class);
        Category::observe(CategoryObserver::class);
        CannedResponse::observe(CannedResponseObserver::class);
    }
}
