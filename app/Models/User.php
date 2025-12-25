<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
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
}
