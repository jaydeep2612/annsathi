<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use League\Csv\Writer;
use SplTempFileObject;

class MenuImportExportService
{
    /**
     * Generate sample CSV template for menu import (NO image column as per requirements).
     */
    public static function generateSampleCsv(): string
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->insertOne([
            'category_name',
            'item_name',
            'description',
            'price',
            'item_type',
            'is_veg',
            'tax_rate',
            'is_available',
        ]);

        $samples = [
            ['Starters', 'Paneer Tikka', 'Tandoori cottage cheese cubes', '240.00', 'prepared', '1', '5.00', '1'],
            ['Beverages', 'Cold Coffee', 'Chilled brewed coffee with cream', '120.00', 'prepared', '1', '5.00', '1'],
            ['Packaged', 'Mineral Water 1L', 'Purified bottled drinking water', '20.00', 'readymade', '1', '0.00', '1'],
        ];

        foreach ($samples as $row) {
            $csv->insertOne($row);
        }

        return $csv->toString();
    }

    /**
     * Import menu from a CSV file.
     * Newly imported items will have blank/placeholder images (image_source = 'none', image_path = null).
     *
     * @return array{imported_items: int, created_categories: int, errors: array}
     */
    public static function importMenuFromCsv(Restaurant $restaurant, string $filePath): array
    {
        $reader = Reader::createFromPath($filePath, 'r');
        $reader->setHeaderOffset(0);

        $records = $reader->getRecords();
        $importedCount = 0;
        $categoryCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            $rowNumber = 1;
            foreach ($records as $offset => $record) {
                $rowNumber = $offset + 1;

                $categoryName = trim($record['category_name'] ?? '');
                $itemName = trim($record['item_name'] ?? '');
                $price = trim($record['price'] ?? '');
                $itemType = strtolower(trim($record['item_type'] ?? 'prepared'));
                if (!in_array($itemType, ['prepared', 'readymade'])) {
                    $itemType = 'prepared';
                }

                if (empty($categoryName) || empty($itemName) || !is_numeric($price)) {
                    $errors[] = "Row {$rowNumber}: Missing category name, item name, or valid price.";
                    continue;
                }

                // 1. Resolve or Create Category for this Restaurant
                $categorySlug = Str::slug($categoryName);
                $category = Category::where('restaurant_id', $restaurant->id)
                    ->where(function ($q) use ($categoryName, $categorySlug) {
                        $q->where('name', $categoryName)
                            ->orWhere('slug', $categorySlug);
                    })
                    ->first();

                if (!$category) {
                    $category = Category::create([
                        'restaurant_id' => $restaurant->id,
                        'name'          => $categoryName,
                        'slug'          => $categorySlug ?: 'category-' . Str::random(5),
                        'is_active'     => true,
                    ]);
                    $categoryCount++;
                }

                // 2. Resolve or Create MenuItem
                $itemSlug = Str::slug($itemName);
                $isVeg = isset($record['is_veg']) ? (bool) trim($record['is_veg']) : true;
                $taxRate = isset($record['tax_rate']) && is_numeric(trim($record['tax_rate'])) ? (float) trim($record['tax_rate']) : null;
                $isAvailable = isset($record['is_available']) ? (bool) trim($record['is_available']) : true;
                $description = trim($record['description'] ?? '');

                // Automatically find best matching photo from global library
                $matchedImage = MenuImageService::findBestMatch($itemName, $categoryName);

                MenuItem::updateOrCreate(
                    [
                        'restaurant_id' => $restaurant->id,
                        'category_id'   => $category->id,
                        'slug'          => $itemSlug ?: 'item-' . Str::random(5),
                    ],
                    [
                        'name'         => $itemName,
                        'description'  => $description ?: null,
                        'price'        => (float) $price,
                        'item_type'    => $itemType,
                        'is_veg'       => $isVeg,
                        'tax_rate'     => $taxRate,
                        'is_available' => $isAvailable,
                        'image_source' => $matchedImage ? 'global' : 'none',
                        'image_path'   => $matchedImage,
                    ]
                );

                $importedCount++;
            }

            DB::commit();

            return [
                'imported_items'     => $importedCount,
                'created_categories' => $categoryCount,
                'errors'             => $errors,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Export all menu items of a restaurant to CSV string.
     */
    public static function exportMenuToCsv(Restaurant $restaurant): string
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->insertOne([
            'category_name',
            'item_name',
            'description',
            'price',
            'item_type',
            'is_veg',
            'tax_rate',
            'is_available',
        ]);

        $items = MenuItem::with('category')
            ->where('restaurant_id', $restaurant->id)
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        foreach ($items as $item) {
            $csv->insertOne([
                $item->category?->name ?? 'Uncategorized',
                $item->name,
                $item->description ?? '',
                number_format((float) $item->price, 2, '.', ''),
                $item->item_type ?? 'prepared',
                $item->is_veg ? '1' : '0',
                $item->tax_rate !== null ? number_format((float) $item->tax_rate, 2, '.', '') : '',
                $item->is_available ? '1' : '0',
            ]);
        }

        return $csv->toString();
    }
}
