<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CategoryRepository extends BaseRepository
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    /**
     * Get active categories
     */
    public function active(): Builder
    {
        return $this->model->where('is_active', true);
    }

    /**
     * Get inactive categories
     */
    public function inactive(): Builder
    {
        return $this->model->where('is_active', false);
    }

    /**
     * Get root categories (no parent)
     */
    public function root(): Builder
    {
        return $this->model->whereNull('parent_id');
    }

    /**
     * Get active root categories with children
     */
    public function getTree(): Collection
    {
        return $this->model->active()
            ->root()
            ->with('children')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get category by slug
     */
    public function findBySlug(string $slug): ?Category
    {
        return $this->model->where('slug', $slug)->first();
    }

    /**
     * Get categories with ticket count
     */
    public function withTicketCount(): Collection
    {
        return $this->model->active()
            ->withCount('tickets')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create category
     */
    public function createCategory(array $data): Category
    {
        if (!isset($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        }

        return $this->create($data);
    }

    /**
     * Update category
     */
    public function updateCategory(int $id, array $data): Category
    {
        $category = $this->findOrFail($id);

        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        }

        $category->update($data);
        return $category->fresh();
    }

    /**
     * Check if category has tickets
     */
    public function hasTickets(int $categoryId): bool
    {
        return $this->model->find($categoryId)->tickets()->count() > 0;
    }

    /**
     * Get all categories as options for select
     */
    public function getSelectOptions(): Collection
    {
        return $this->model->active()
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
