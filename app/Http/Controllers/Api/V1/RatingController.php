<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\RatingRepository;
use App\Repositories\TicketRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RatingController extends Controller
{
    protected RatingRepository $ratingRepo;
    protected TicketRepository $ticketRepo;

    public function __construct(
        RatingRepository $ratingRepo,
        TicketRepository $ticketRepo
    ) {
        $this->ratingRepo = $ratingRepo;
        $this->ticketRepo = $ticketRepo;
    }

    /**
     * Get rating for a specific ticket
     */
    public function show(Request $request, int $ticketId): JsonResponse
    {
        $ticket = $this->ticketRepo->findOrFail($ticketId);

        // Check if user owns this ticket
        if ($request->user()->id !== $ticket->user_id && !$request->user()->isCsKH()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $rating = $this->ratingRepo->findByTicketId($ticketId);

        if (!$rating) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $rating->id,
                'ticket_id' => $rating->ticket_id,
                'rating' => (float) number_format($rating->rating, 1),
                'comment' => $rating->comment,
                'rated_user' => $rating->ratedUser ? [
                    'id' => $rating->ratedUser->id,
                    'name' => $rating->ratedUser->name,
                ] : null,
                'created_at' => $rating->created_at,
            ],
        ]);
    }

    /**
     * Create a rating for a ticket
     */
    public function store(Request $request, int $ticketId): JsonResponse
    {
        $user = $request->user();

        // CSKH/Admin cannot rate tickets
        if ($user->isCsKH()) {
            return response()->json([
                'success' => false,
                'message' => 'CSKH staff cannot rate tickets',
            ], 403);
        }

        $ticket = $this->ticketRepo->findOrFail($ticketId);

        // Check if user owns this ticket
        if ($user->id !== $ticket->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only rate your own tickets',
            ], 403);
        }

        // Validate rating
        $validated = $request->validate([
            'rating' => 'required|numeric|min:1|max:5|regex:/^\d+(\.5)?$/',
            'comment' => 'nullable|string|max:1000',
        ], [
            'rating.regex' => 'Rating must be in increments of 0.5 (e.g., 1, 1.5, 2, 2.5, etc.)',
        ]);

        try {
            $rating = $this->ratingRepo->createForTicket($ticketId, [
                'user_id' => $user->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cảm ơn bạn đã đánh giá!',
                'data' => [
                    'id' => $rating->id,
                    'ticket_id' => $rating->ticket_id,
                    'rating' => (float) number_format($rating->rating, 1),
                    'comment' => $rating->comment,
                    'created_at' => $rating->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all CSKH rating statistics for dashboard
     * CSKH/Admin only
     */
    public function cskhStats(Request $request): JsonResponse
    {
        if (!$request->user()->isCsKH()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $days = (int) $request->get('days', 30);

        $cskhStats = $this->ratingRepo->getAllCskhStats($days);
        $overallStats = $this->ratingRepo->getOverallStats($days);

        return response()->json([
            'success' => true,
            'data' => [
                'average_rating' => $overallStats['average_rating'],
                'total_ratings' => $overallStats['total_ratings'],
                'distribution' => $overallStats['distribution'],
                'cskh_staff' => $cskhStats,
            ],
        ]);
    }

    /**
     * Get current CSKH's own statistics
     * CSKH/Admin only
     */
    public function myStats(Request $request): JsonResponse
    {
        if (!$request->user()->isCsKH()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $days = (int) $request->get('days', 30);
        $stats = $this->ratingRepo->getStatsForUser($request->user()->id, $days);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
