<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Traits\Auditable;

class MenuItem extends Model
{
    use Auditable;

    protected $fillable = [
        'restaurant_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'tax_rate',
        'item_type',
        'image_source',
        'image_path',
        'is_available',
        'track_stock',
        'stock_quantity',
        'low_stock_threshold',
        'is_veg',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'tax_rate'            => 'decimal:2',
        'is_available'        => 'boolean',
        'is_veg'              => 'boolean',
        'track_stock'         => 'boolean',
        'stock_quantity'      => 'integer',
        'low_stock_threshold' => 'integer',
    ];

    // ── Virtual Type Attribute for Backward Compatibility ───────
    public function getTypeAttribute(): string
    {
        return $this->is_veg ? 'veg' : 'non-veg';
    }

    public function setTypeAttribute($value): void
    {
        $this->attributes['is_veg'] = ($value === 'veg' || $value === true || $value === 1 || $value === '1');
    }

    // ── Scopes ──────────────────────────────────────────────
    public function scopeLowStock($query)
    {
        return $query->where('track_stock', true)
            ->whereNotNull('stock_quantity')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('track_stock', true)
            ->where('stock_quantity', 0);
    }

    // ── Accessors ────────────────────────────────────────────
    public function getStockStatusAttribute(): string
    {
        if (!$this->track_stock || $this->stock_quantity === null)
            return 'untracked';
        if ($this->stock_quantity <= 0)
            return 'out_of_stock';
        if ($this->stock_quantity <= $this->low_stock_threshold)
            return 'low';
        return 'ok';
    }

    // ── Helpers ──────────────────────────────────────────────
    public static function deductStockForOrder(\App\Models\Order $order): void
    {
        $items = $order->items ?? $order->orderItems;
        if (!$items) return;

        foreach ($items as $orderItem) {
            if (!$orderItem->menu_item_id)
                continue;

            $menuItem = static::find($orderItem->menu_item_id);
            if (!$menuItem || !$menuItem->track_stock || $menuItem->stock_quantity === null)
                continue;

            $newQty = max(0, $menuItem->stock_quantity - $orderItem->quantity);
            $menuItem->update(['stock_quantity' => $newQty]);

            // Auto mark unavailable when stock hits 0
            if ($newQty === 0) {
                $menuItem->update(['is_available' => false]);
            }
        }
    }

    // ── Relations ────────────────────────────────────────────
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function hasRecipes(): bool
    {
        return $this->recipes()->exists();
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image_path)) {
            return null;
        }

        if (Str::startsWith($this->image_path, ['http://', 'https://'])) {
            return $this->image_path;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    protected static function booted()
    {
        static::creating(function ($item) {
            if (empty($item->slug) && !empty($item->name)) {
                $item->slug = Str::slug($item->name);
            }

            // Auto-assign matching global image on initial creation if no image was provided
            if (empty($item->image_path) && !empty($item->name)) {
                $matched = \App\Services\MenuImageService::findBestMatch($item->name, $item->category?->name);
                if ($matched) {
                    $item->image_path = $matched;
                    $item->image_source = 'global';
                }
            }
        });

        static::saved(function (MenuItem $item) {
            // If custom uploaded image, automatically contribute unique photo to global library
            if ($item->image_source === 'custom' && $item->image_path && !Str::startsWith($item->image_path, 'menu_images/')) {
                \App\Services\MenuImageService::contributeToGlobalLibrary($item->image_path, $item->name);
            }
        });

        static::deleting(function (MenuItem $item) {
            // Only delete physical file if it was a custom upload, never delete shared global library images
            if ($item->image_source === 'custom' && $item->image_path && Storage::disk('public')->exists($item->image_path)) {
                Storage::disk('public')->delete($item->image_path);
            }
        });
    }
}