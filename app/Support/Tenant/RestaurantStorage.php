<?php

namespace App\Support\Tenant;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Storage;

class RestaurantStorage
{
    public static function base(Restaurant $restaurant): string
    {
        return "restaurants/{$restaurant->slug}";
    }

    public static function logoPath(Restaurant $restaurant): string
    {
        return self::base($restaurant) . '/LOGO';
    }

    public static function categoryPath(Restaurant $restaurant, string $categorySlug): string
    {
        return self::base($restaurant) . "/Categories/{$categorySlug}";
    }

    public static function tableQrPath(Restaurant $restaurant): string
    {
        return self::base($restaurant) . '/TablesQR';
    }
}
