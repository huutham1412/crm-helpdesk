<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\ActivityLogService;

class CategoryObserver
{
    public function __construct(protected ActivityLogService $logger)
    {
    }

    public function created(Category $category): void
    {
        $this->logger->log(
            'category.created',
            $category,
            "Tạo danh mục: {$category->name}",
            [],
            'info',
            ['category']
        );
    }

    public function updated(Category $category): void
    {
        $changes = [];
        if ($category->isDirty('name')) {
            $changes['name'] = [
                'old' => $category->getOriginal('name'),
                'new' => $category->name
            ];
        }

        if ($category->isDirty('parent_id')) {
            $oldParent = \App\Models\Category::find($category->getOriginal('parent_id'));
            $newParent = $category->parent;
            $changes['parent_id'] = [
                'old' => $oldParent?->name ?? 'None',
                'new' => $newParent?->name ?? 'None'
            ];
        }

        if (!empty($changes)) {
            $this->logger->log(
                'category.updated',
                $category,
                "Cập nhật danh mục: {$category->name}",
                $changes,
                'info',
                ['category']
            );
        }
    }

    public function deleted(Category $category): void
    {
        $this->logger->log(
            'category.deleted',
            $category,
            "Xóa danh mục: {$category->name}",
            [],
            'warning',
            ['category']
        );
    }
}
