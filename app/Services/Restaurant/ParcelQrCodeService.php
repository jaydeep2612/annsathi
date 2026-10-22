<?php

namespace App\Services\Restaurant;

use App\Models\ParcelQrCode;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ParcelQrCodeService
{
    public function generate(ParcelQrCode $parcelQr): string
    {
        $restaurant = $parcelQr->restaurant;
        $restaurantSlug = Str::slug($restaurant->name ?? 'restaurant');

        $folder = "restaurants/{$restaurantSlug}/ParcelQR";

        $safeName = Str::slug($parcelQr->name);
        $filename = "{$safeName}-{$parcelQr->id}.svg"; 

        Storage::disk('public')->makeDirectory($folder);

        $url = 'https://customer.annsathi.com'
            . "/?type=parcel"
            . "&r={$restaurant->id}"
            . "&id={$parcelQr->id}"
            . "&token={$parcelQr->qr_token}";

        $qrSvg = QrCode::format('svg')->size(300)->margin(1)->color(0, 0, 0)->generate($url);

        Storage::disk('public')->put("{$folder}/{$filename}", $qrSvg);
        $parcelQr->updateQuietly(['qr_path' => "{$folder}/{$filename}"]);

        return "{$folder}/{$filename}";
    }
}
