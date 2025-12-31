<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasEvents;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'avg_rating',
        'total_ratings',
        'rating_distribution',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationships
    public function createdTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function receivedRatings(): HasMany
    {
        return $this->hasMany(Rating::class, 'rated_user_id');
    }

    public function givenRatings(): HasMany
    {
        return $this->hasMany(Rating::class, 'user_id');
    }

    // Helper methods for role checking
    public function isCsKH(): bool
    {
        return $this->hasAnyRole(['Admin', 'CSKH']);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('Admin');
    }

    public function isRegularUser(): bool
    {
        return $this->hasRole('User');
    }

    /**
     * Get all tickets that this user can access
     * - Regular users: only their own tickets
     * - CSKH/Admin: all tickets or assigned tickets
     */
    public function accessibleTickets()
    {
        if ($this->isAdmin()) {
            return Ticket::query();
        }

        if ($this->isCsKH()) {
            return Ticket::where(function ($query) {
                $query->whereNull('assigned_to')
                    ->orWhere('assigned_to', $this->id);
            });
        }

        return $this->createdTickets();
    }

    /**
     * Get avatar URL
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return Storage::url($this->avatar);
        }

        return 'https://www.gravatar.com/avatar/' . md5(strtolower($this->email));
    }

    /**
     * Get full name with fallback
     */
    public function getFullNameAttribute(): string
    {
        return $this->name ?? $this->email ?? 'Unknown';
    }

    /**
     * Update rating statistics for this user
     * Should be called after a new rating is received
     */
    public function updateRatingStats(): void
    {
        $ratings = $this->receivedRatings;

        $this->avg_rating = $ratings->avg('rating');
        $this->total_ratings = $ratings->count();

        // Calculate distribution: {1: x, 2: y, 3: z, 4: w, 5: v}
        $distribution = [
            '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0
        ];

        foreach ($ratings as $rating) {
            $starValue = (int) round($rating->rating);
            if ($starValue >= 1 && $starValue <= 5) {
                $distribution[(string) $starValue]++;
            }
        }

        $this->rating_distribution = $distribution;

        // Use DB update to avoid triggering model events
        $this->newQuery()->where('id', $this->id)->update([
            'avg_rating' => $this->avg_rating,
            'total_ratings' => $this->total_ratings,
            'rating_distribution' => $this->rating_distribution,
        ]);
    }
}
