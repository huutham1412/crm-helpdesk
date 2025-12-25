<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\TicketRepository;
use App\Repositories\MessageRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    protected TicketRepository $ticketRepo;
    protected MessageRepository $messageRepo;
    protected UserRepository $userRepo;

    public function __construct(
        TicketRepository $ticketRepo,
        MessageRepository $messageRepo,
        UserRepository $userRepo
    ) {
        $this->ticketRepo = $ticketRepo;
        $this->messageRepo = $messageRepo;
        $this->userRepo = $userRepo;
    }

    /**
     * Get dashboard statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        // Basic stats
        $ticketStats = $this->ticketRepo->getStatistics($user);

        $stats = [
            'total_tickets' => $ticketStats['total'],
            'open_tickets' => $ticketStats['open'],
            'resolved_tickets' => $ticketStats['resolved'],
            'closed_tickets' => $ticketStats['closed'],
            'pending_tickets' => $ticketStats['pending'],
        ];

        // Only CSKH/Admin gets additional stats
        if ($user->isCsKH()) {
            // Average resolution time (in minutes)
            $stats['avg_resolution_time'] = $this->ticketRepo->getAverageResolutionTime();

            // Tickets by priority
            $stats['tickets_by_priority'] = $this->ticketRepo->getByPriorityStats();

            // Tickets by status
            $stats['tickets_by_status'] = $this->ticketRepo->getByStatusStats();

            // Tickets by category
            $stats['tickets_by_category'] = $this->ticketRepo->getByCategoryStats();

            // Last 30 days trend
            $stats['tickets_trend'] = $this->ticketRepo->getTrend(30);

            // User statistics
            $userStats = $this->userRepo->getStatistics();
            $stats['total_users'] = $userStats['total'];
            $stats['cskh_users'] = $userStats['cskh'];
        }

        // Recent tickets for current user
        $stats['recent_tickets'] = $this->ticketRepo->recent(5);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get recent activity (CSKH/Admin only)
     */
    public function recentActivity(Request $request): JsonResponse
    {
        if (!$request->user()->isCsKH()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $recentTickets = $this->ticketRepo->recent(10);

        $recentMessages = $this->messageRepo->recent(10);

        $recentUsers = $this->userRepo->recent(5);

        return response()->json([
            'success' => true,
            'data' => [
                'recent_tickets' => $recentTickets,
                'recent_messages' => $recentMessages,
                'recent_users' => $recentUsers,
            ],
        ]);
    }
}
