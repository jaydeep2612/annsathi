<?php
namespace App\Services\Restaurant;

use App\Models\RestaurantTable;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrCodeService
{
    public function generate(RestaurantTable $table): string
    {
        $restaurant = $table->restaurant;
        $restaurantSlug = Str::slug($restaurant->name ?? 'restaurant');

        $folder = "restaurants/{$restaurantSlug}/TableQR";

        $safeTableNum = Str::slug($table->table_number);
        $filename = "{$safeTableNum}.svg";

        Storage::disk('public')->makeDirectory($folder);

        $url = "https://customer.annsathi.com/?r={$restaurant->id}&t={$table->id}&token={$table->qr_token}";

        // 👇 Generates ONLY the raw, basic QR Code. No wrappers, no text.
        $qrSvg = QrCode::format('svg')
            ->size(300) 
            ->margin(1)
            ->color(0, 0, 0)
            ->generate($url);

        Storage::disk('public')->put("{$folder}/{$filename}", $qrSvg);

        $table->updateQuietly([
            'qr_path' => "{$folder}/{$filename}",
        ]);

        return "{$folder}/{$filename}";
    }
}
