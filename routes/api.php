<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\BroadcastController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\RatingController;
use App\Http\Controllers\Api\V1\CannedResponseController;
use App\Http\Controllers\Api\V1\EscalationController;

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
            Route::get('/dashboard/analytics', [DashboardController::class, 'analytics']);
            Route::get('/dashboard/charts', [DashboardController::class, 'charts']);
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

        // ==================== ATTACHMENT ROUTES ====================
        Route::get('/tickets/{ticketId}/attachments', [AttachmentController::class, 'index']);
        Route::post('/tickets/{ticketId}/attachments', [AttachmentController::class, 'upload']);
        Route::get('/attachments/{id}', [AttachmentController::class, 'show']);
        Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy']);

        // ==================== TYPING INDICATOR ROUTE ====================
        Route::post('/tickets/{ticket}/typing', [MessageController::class, 'typing']);

        // ==================== READ RECEIPT ROUTE ====================
        Route::post('/tickets/{ticketId}/messages/read', [MessageController::class, 'markAsRead']);

        // ==================== BROADCASTING AUTH ROUTE ====================
        Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate']);

        // ==================== RATING ROUTES ====================
        Route::get('/tickets/{ticketId}/rating', [RatingController::class, 'show']);
        Route::post('/tickets/{ticketId}/rating', [RatingController::class, 'store']);

        // Rating stats (CSKH/Admin only)
        Route::middleware('role:CSKH|Admin')->group(function () {
            Route::get('/ratings/cskh-stats', [RatingController::class, 'cskhStats']);
            Route::get('/ratings/my-stats', [RatingController::class, 'myStats']);
        });

        // ==================== CANNED RESPONSE ROUTES ====================
        // CSKH/Admin only
        Route::middleware('role:CSKH|Admin')->group(function () {
            Route::get('/canned-responses', [CannedResponseController::class, 'index']);
            Route::get('/canned-responses/all', [CannedResponseController::class, 'getAllActive']);
            Route::get('/canned-responses/popular', [CannedResponseController::class, 'popular']);
            Route::get('/canned-responses/{id}', [CannedResponseController::class, 'show']);
            Route::post('/canned-responses', [CannedResponseController::class, 'store']);
            Route::put('/canned-responses/{id}', [CannedResponseController::class, 'update']);
            Route::delete('/canned-responses/{id}', [CannedResponseController::class, 'destroy']);
            Route::post('/canned-responses/{id}/preview', [CannedResponseController::class, 'preview']);
            Route::post('/canned-responses/{id}/use', [CannedResponseController::class, 'incrementUsage']);
        });

        // ==================== ESCALATION ROUTES ====================
        // Admin only - View escalation history and statistics
        Route::middleware('role:Admin')->group(function () {
            Route::get('/escalations', [EscalationController::class, 'index']);
            Route::get('/escalations/statistics', [EscalationController::class, 'statistics']);
            Route::get('/escalations/{id}', [EscalationController::class, 'show']);
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
