<?php

namespace App\Services\Restaurant;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class QrZipService
{
    /**
     * 🔹 ZIP ALL tables of a restaurant
     */
    public function createForRestaurant(Restaurant $restaurant, $user = null): string
    {
        $query = $restaurant->tables()->whereNotNull('qr_path');
        $zipName = $restaurant->slug;

        return $this->buildZip(
            $zipName,
            $query->get()
        );
    }

    /**
     * 🔹 ZIP ONLY selected tables
     */
    public function createForTables(Collection $tables): string
    {
        if ($tables->isEmpty()) {
            throw new RuntimeException('No tables selected');
        }

        $restaurant = $tables->first()->restaurant;

        return $this->buildZip(
            $restaurant->slug . '-selected',
            $tables->whereNotNull('qr_path')
        );
    }

    /**
     * 🔧 Internal ZIP builder
     */
    protected function buildZip(string $name, Collection $tables): string
    {
        if ($tables->isEmpty()) {
            throw new RuntimeException('No QR codes available to download');
        }

        $tempDir = storage_path('app/temp');
        $zipPath = "{$tempDir}/{$name}-table-qrs.zip";

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create ZIP archive');
        }

        foreach ($tables as $table) {
            $absolutePath = storage_path("app/public/{$table->qr_path}");

            if (file_exists($absolutePath)) {
                $zip->addFile(
                    $absolutePath,
                    "TablesQR/Table-{$table->table_number}.svg" // 👈 Same format as SVG
                );
            }
        }

        $zip->close();

        if (!file_exists($zipPath)) {
            throw new RuntimeException('ZIP file was not created');
        }

        return $zipPath;
    }
}
