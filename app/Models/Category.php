<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Traits\Auditable;

class Category extends Model
{
    use Auditable;

    protected $fillable = [
        'restaurant_id',
        'name',
        'slug',
        'description',
        'image_path',
        'display_order',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'display_order' => 'integer',
        'sort_order'    => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($category) {
            if (empty($category->slug) && !empty($category->name)) {
                $category->slug = \Illuminate\Support\Str::slug($category->name);
            }
        });

        static::deleting(function ($category) {
            $category->menuItems()->delete();
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}