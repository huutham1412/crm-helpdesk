<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Event;
use App\Listeners\LogAuthentication;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: []);

        // Enable CORS globally (array format)
        $middleware->use([\Illuminate\Http\Middleware\HandleCors::class]);

        // Register Spatie role middleware
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            return $request->expectsJson() ? null : '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
