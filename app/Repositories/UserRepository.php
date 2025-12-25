<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Get CSKH users (Admin and CSKH roles)
     */
    public function cskh(): Collection
    {
        return $this->model->role(['Admin', 'CSKH'])->get();
    }

    /**
     * Get regular users
     */
    public function regularUsers(): Collection
    {
        return $this->model->role('User')->get();
    }

    /**
     * Get users by role
     */
    public function byRole(string $role): Collection
    {
        return $this->model->role($role)->get();
    }

    /**
     * Search users
     */
    public function search(string $keyword): Collection
    {
        return $this->model->where(function ($query) use ($keyword) {
            $query->where('name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%")
                ->orWhere('phone', 'like', "%{$keyword}%");
        })->get();
    }

    /**
     * Get online users
     */
    public function online(): Collection
    {
        return $this->model->where('is_online', true)->get();
    }

    /**
     * Get paginated users with filters
     */
    public function paginateWithFilters(
        int $perPage = 20,
        ?string $search = null,
        ?string $role = null
    ): LengthAwarePaginator {
        $query = $this->model->with('roles', 'permissions');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get CSKH staff for select dropdown
     */
    public function getCskhForSelect(): Collection
    {
        return $this->model->role(['Admin', 'CSKH'])
            ->get(['id', 'name', 'email']);
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Update user profile
     */
    public function updateProfile(int $userId, array $data): User
    {
        $user = $this->findOrFail($userId);

        $allowedFields = ['name', 'phone', 'avatar'];
        $filteredData = array_intersect_key($data, array_flip($allowedFields));

        $user->update($filteredData);
        return $user->fresh();
    }

    /**
     * Update user password
     */
    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        $user = $this->findOrFail($userId);
        $user->password = $hashedPassword;
        return $user->save();
    }

    /**
     * Update user role
     */
    public function updateRole(int $userId, string $role): User
    {
        $user = $this->findOrFail($userId);
        $user->syncRoles([$role]);
        return $user->fresh();
    }

    /**
     * Get user statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->model->count(),
            'cskh' => $this->model->role(['Admin', 'CSKH'])->count(),
            'users' => $this->model->role('User')->count(),
            'online' => $this->model->where('is_online', true)->count(),
        ];
    }

    /**
     * Get recent users
     */
    public function recent(int $limit = 5): Collection
    {
        return $this->model->latest()
            ->limit($limit)
            ->get(['id', 'name', 'email', 'created_at']);
    }

    /**
     * Check if user has role
     */
    public function hasRole(int $userId, string $role): bool
    {
        $user = $this->find($userId);
        return $user && $user->hasRole($role);
    }

    /**
     * Check if user has any of the roles
     */
    public function hasAnyRole(int $userId, array $roles): bool
    {
        $user = $this->find($userId);
        return $user && $user->hasAnyRole($roles);
    }
}
