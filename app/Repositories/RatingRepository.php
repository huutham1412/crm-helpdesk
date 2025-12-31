<?php

namespace App\Repositories;

use App\Models\Rating;
use App\Models\Ticket;
use App\Models\User;
use App\Events\TicketRated;
use Illuminate\Support\Collection;

class RatingRepository extends BaseRepository
{
    public function __construct(Rating $model)
    {
        parent::__construct($model);
    }

    /**
     * Create rating for a ticket
     */
    public function createForTicket(int $ticketId, array $data): Rating
    {
        $ticket = Ticket::findOrFail($ticketId);

        if ($ticket->status !== 'closed') {
            throw new \Exception('Ticket must be closed before rating');
        }

        // Check if already rated
        if ($ticket->rating) {
            throw new \Exception('Ticket has already been rated');
        }

        // Get the CSKH user who handled the ticket
        $ratedUserId = $ticket->assigned_to ?? $ticket->category?->assigned_to ?? null;

        if (!$ratedUserId) {
            throw new \Exception('No CSKH user assigned to this ticket');
        }

        $rating = $this->model->create([
            'ticket_id' => $ticketId,
            'user_id' => $data['user_id'],
            'rated_user_id' => $ratedUserId,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

        // Update CSKH user's rating stats
        $ratedUser = User::find($ratedUserId);
        if ($ratedUser) {
            $ratedUser->updateRatingStats();
        }

        // Broadcast the rating event
        broadcast(new TicketRated($rating));

        return $rating;
    }

    /**
     * Get rating statistics for a specific user
     */
    public function getStatsForUser(int $userId, int $days = 30): array
    {
        $query = $this->model->forRatedUser($userId);

        if ($days > 0) {
            $query->inPeriod($days);
        }

        $ratings = $query->get();

        $totalRatings = $ratings->count();
        $averageRating = $totalRatings > 0 ? $ratings->avg('rating') : 0;

        // Calculate distribution
        $distribution = [
            '5_star' => 0,
            '4_star' => 0,
            '3_star' => 0,
            '2_star' => 0,
            '1_star' => 0,
        ];

        foreach ($ratings as $rating) {
            $starValue = (int) round($rating->rating);
            $key = $starValue . '_star';
            if (isset($distribution[$key])) {
                $distribution[$key]++;
            }
        }

        return [
            'average_rating' => $averageRating ? round($averageRating, 2) : null,
            'total_ratings' => $totalRatings,
            'distribution' => $distribution,
        ];
    }

    /**
     * Get all CSKH staff rating statistics for dashboard
     */
    public function getAllCskhStats(int $days = 30): Collection
    {
        // Get all CSKH users with their ratings
        $cskhUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['Admin', 'CSKH']);
        })
            ->with(['receivedRatings' => function ($query) use ($days) {
                if ($days > 0) {
                    $query->inPeriod($days);
                }
            }])
            ->get();

        return $cskhUsers->map(function ($user) use ($days) {
            $ratings = $user->receivedRatings;
            $totalRatings = $ratings->count();

            $distribution = [
                '5' => 0,
                '4' => 0,
                '3' => 0,
                '2' => 0,
                '1' => 0,
            ];

            foreach ($ratings as $rating) {
                $starValue = (int) round($rating->rating);
                if ($starValue >= 1 && $starValue <= 5) {
                    $distribution[(string) $starValue]++;
                }
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'avg_rating' => $totalRatings > 0 ? round($ratings->avg('rating'), 2) : null,
                'total_ratings' => $totalRatings,
                'distribution' => $distribution,
            ];
        })->sortByDesc('avg_rating')->values();
    }

    /**
     * Get overall rating statistics
     */
    public function getOverallStats(int $days = 30): array
    {
        $query = $this->model->query();

        if ($days > 0) {
            $query->inPeriod($days);
        }

        $ratings = $query->get();
        $totalRatings = $ratings->count();
        $averageRating = $totalRatings > 0 ? $ratings->avg('rating') : 0;

        // Calculate distribution
        $distribution = [
            '5_star' => 0,
            '4_star' => 0,
            '3_star' => 0,
            '2_star' => 0,
            '1_star' => 0,
        ];

        foreach ($ratings as $rating) {
            $starValue = (int) round($rating->rating);
            $key = $starValue . '_star';
            if (isset($distribution[$key])) {
                $distribution[$key]++;
            }
        }

        return [
            'average_rating' => $averageRating ? round($averageRating, 2) : null,
            'total_ratings' => $totalRatings,
            'distribution' => $distribution,
        ];
    }

    /**
     * Find rating by ticket ID
     */
    public function findByTicketId(int $ticketId): ?Rating
    {
        return $this->model->where('ticket_id', $ticketId)->first();
    }
}
