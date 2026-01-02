<?php

namespace App\Repositories;

use App\Models\CannedResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CannedResponseRepository extends BaseRepository
{
    public function __construct(CannedResponse $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all active canned responses with relationships.
     */
    public function getAllActive(array $filters = []): Collection
    {
        $query = $this->model->newQuery()
            ->active()
            ->with(['user', 'category']);

        // Apply filters
        if (isset($filters['category_id'])) {
            $query->byCategory($filters['category_id']);
        }

        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->latest()->get();
    }

    /**
     * Get paginated canned responses.
     */
    public function paginateWithFilters(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with(['user', 'category']);

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by category
        if (isset($filters['category_id'])) {
            $query->byCategory($filters['category_id']);
        }

        // Search
        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        // Filter by user (for personal templates)
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Find canned response by ID with relationships.
     */
    public function findByIdWithRelations(int $id): ?Model
    {
        return $this->model->with(['user', 'category'])->find($id);
    }

    /**
     * Create canned response and extract variables.
     */
    public function create(array $data): Model
    {
        // Extract variables from content if not provided
        if (!isset($data['variables'])) {
            $data['variables'] = $this->extractVariables($data['content'] ?? '');
        }

        return $this->model->create($data);
    }

    /**
     * Update canned response.
     */
    public function update(int $id, array $data): Model
    {
        $record = $this->findOrFail($id);

        // Re-extract variables if content changed
        if (isset($data['content']) && !isset($data['variables'])) {
            $data['variables'] = $this->extractVariables($data['content']);
        }

        $record->update($data);
        return $record->fresh();
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(int $id): void
    {
        $record = $this->findOrFail($id);
        $record->incrementUsage();
    }

    /**
     * Get popular canned responses (most used).
     */
    public function getPopular(int $limit = 10): Collection
    {
        return $this->model->active()
            ->with(['user', 'category'])
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get canned responses by category.
     */
    public function getByCategory(int $categoryId): Collection
    {
        return $this->model->active()
            ->with(['user', 'category'])
            ->byCategory($categoryId)
            ->latest()
            ->get();
    }

    /**
     * Get canned responses created by user.
     */
    public function getByUser(int $userId): Collection
    {
        return $this->model->active()
            ->where('user_id', $userId)
            ->with(['user', 'category'])
            ->latest()
            ->get();
    }

    /**
     * Extract variables from content.
     */
    protected function extractVariables(string $content): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $content, $matches);

        return $matches[1] ?? [];
    }
}
