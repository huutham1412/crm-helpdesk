<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CannedResponse extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'content',
        'variables',
        'usage_count',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'variables' => 'array',
        'usage_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relationship: Canned response belongs to a user (creator).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Canned response belongs to a category (optional).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Render content by replacing variables with actual data.
     */
    public function renderContent(array $data = []): string
    {
        $content = $this->content;

        // Define available variables and their replacements
        $variables = [
            '{customer_name}' => $data['customer_name'] ?? '',
            '{ticket_number}' => $data['ticket_number'] ?? '',
            '{category}' => $data['category'] ?? '',
            '{cskh_name}' => $data['cskh_name'] ?? '',
            '{subject}' => $data['subject'] ?? '',
        ];

        // Replace variables in content
        foreach ($variables as $key => $value) {
            $content = str_replace($key, $value, $content);
        }

        return $content;
    }

    /**
     * Scope to only include active responses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory($query, $categoryId)
    {
        if ($categoryId) {
            return $query->where('category_id', $categoryId);
        }
        return $query;
    }

    /**
     * Scope to search by title or content.
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }
        return $query;
    }
}
