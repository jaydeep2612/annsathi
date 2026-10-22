<?php

namespace App\Filament\Resources\MenuItemResource\Pages;

use App\Filament\Resources\MenuItemResource;
use App\Services\MenuImportExportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListMenuItems extends ListRecords
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_sample')
                ->label('Sample CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->tooltip('Download a starter template CSV without image columns')
                ->action(function () {
                    $csvContent = MenuImportExportService::generateSampleCsv();
                    return response()->streamDownload(function () use ($csvContent) {
                        echo $csvContent;
                    }, 'menu_import_sample.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),

            Actions\Action::make('import_menu')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->modalHeading('Bulk Import Menu Items')
                ->modalDescription('Upload your menu CSV file. Items will be imported with blank images. You can assign shared global library images or custom photos later.')
                ->form([
                    Forms\Components\FileUpload::make('csv_file')
                        ->label('Select CSV File')
                        ->disk('local')
                        ->directory('temp-menu-imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv'])
                        ->required()
                        ->helperText('Expected format: category_name, item_name, description, price, item_type, is_veg, tax_rate, is_available'),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    $restaurant = $user->restaurant;

                    if (!$restaurant) {
                        Notification::make()
                            ->title('Restaurant Not Found')
                            ->body('Your user account is not linked to an active restaurant.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $filePath = Storage::disk('local')->path($data['csv_file']);

                    try {
                        $result = MenuImportExportService::importMenuFromCsv($restaurant, $filePath);

                        $msg = "Successfully imported {$result['imported_items']} items across {$result['created_categories']} categories.";
                        if (!empty($result['errors'])) {
                            $msg .= " (" . count($result['errors']) . " rows had issues and were skipped)";
                        }

                        Notification::make()
                            ->title('Menu Import Completed')
                            ->body($msg)
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Import Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        if (Storage::disk('local')->exists($data['csv_file'])) {
                            Storage::disk('local')->delete($data['csv_file']);
                        }
                    }
                }),

            Actions\Action::make('export_menu')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->tooltip('Export all menu items and categories to CSV')
                ->action(function () {
                    $user = auth()->user();
                    $restaurant = $user->restaurant;

                    if (!$restaurant) {
                        Notification::make()
                            ->title('Restaurant Not Found')
                            ->danger()
                            ->send();
                        return;
                    }

                    $csvContent = MenuImportExportService::exportMenuToCsv($restaurant);
                    $filename = Str::slug($restaurant->name) . '_menu_' . now()->format('Y-m-d') . '.csv';

                    return response()->streamDownload(function () use ($csvContent) {
                        echo $csvContent;
                    }, $filename, [
                        'Content-Type' => 'text/csv',
                    ]);
                }),

            Actions\CreateAction::make()
                ->label('Add Menu Item')
                ->icon('heroicon-o-plus')
                ->color('warning'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MenuItemResource\Widgets\MenuItemStats::class,
        ];
    }
}