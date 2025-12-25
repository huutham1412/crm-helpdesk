<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Get setting value by key
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean', 'bool' => (bool) $setting->value,
            'integer', 'int' => (int) $setting->value,
            'float', 'double' => (float) $setting->value,
            'array', 'json' => is_array($setting->value) ? $setting->value : json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    /**
     * Set setting value by key (create or update)
     */
    public static function set(string $key, $value, string $type = 'string', string $group = 'general'): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : $value,
                'type' => $type,
                'group' => $group,
            ]
        );
    }

    // Scopes
    public function scopeByGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Group constants
     */
    public const GROUP_GENERAL = 'general';
    public const GROUP_TELEGRAM = 'telegram';
    public const GROUP_EMAIL = 'email';
    public const GROUP_NOTIFICATION = 'notification';
}
