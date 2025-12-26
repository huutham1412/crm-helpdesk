<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RoleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the Laravel API RouteServiceProvider.
|
*/

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth routes
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        // Ticket routes
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::post('/tickets', [TicketController::class, 'store']);
        Route::get('/tickets/{id}', [TicketController::class, 'show']);

        // CSKH/Admin only ticket routes
        Route::middleware('role:CSKH|Admin')->group(function () {
            Route::put('/tickets/{id}', [TicketController::class, 'update']);
            Route::post('/tickets/{id}/assign', [TicketController::class, 'assign']);
            Route::delete('/tickets/{id}', [TicketController::class, 'destroy']);
        });

        // Message routes
        Route::get('/tickets/{ticketId}/messages', [MessageController::class, 'index']);
        Route::post('/tickets/{ticketId}/messages', [MessageController::class, 'store']);

        // Category routes
        Route::get('/categories', [CategoryController::class, 'index']);

        // Dashboard routes
        Route::get('/dashboard/statistics', [DashboardController::class, 'statistics']);

        // CSKH/Admin only dashboard routes
        Route::middleware('role:CSKH|Admin')->group(function () {
            Route::get('/dashboard/activity', [DashboardController::class, 'recentActivity']);
        });

        // Notification routes
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

        // User routes (CSKH/Admin)
        Route::middleware('role:CSKH|Admin')->group(function () {
            Route::get('/users/cskh-list', [UserController::class, 'cskhList']);
        });

        // ==================== ADMIN ONLY ROUTES ====================
        Route::middleware('role:Admin')->group(function () {
            // User management
            Route::apiResource('users', UserController::class);
            Route::put('/users/{user}/roles', [UserController::class, 'assignRoles']);
            Route::put('/users/{user}/status', [UserController::class, 'toggleStatus']);
            Route::get('/users/statistics', [UserController::class, 'statistics']);

            // Role management
            Route::apiResource('roles', RoleController::class);
            Route::get('/roles/list', [RoleController::class, 'list']);

            // Category management
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{id}', [CategoryController::class, 'update']);
            Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
        });
    });
});
