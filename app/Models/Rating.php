<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'rated_user_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
    ];

    /**
     * Get the ticket that was rated
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who gave the rating
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the CSKH user who was rated
     */
    public function ratedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_user_id');
    }

    /**
     * Scope to filter by rated user
     */
    public function scopeForRatedUser($query, $userId)
    {
        return $query->where('rated_user_id', $userId);
    }

    /**
     * Scope to filter ratings within a period
     */
    public function scopeInPeriod($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get rating display value (e.g., 4.5 -> "4.5 sao")
     */
    public function getDisplayRatingAttribute(): string
    {
        return number_format($this->rating, 1) . ' sao';
    }
}
