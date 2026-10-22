<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use App\Models\Category;
use App\Models\MenuItem;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Resources\Components\Tab;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

class ManageMenus extends ManageRecords
{
    protected static string $resource = MenuResource::class;

    public function getTabs(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MenuResource\Widgets\MenuDashboardStats::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        $bgImageUrl = asset('images/bg.png');

        // 👇 GLOBAL STYLES FOR PAGE BACKGROUND, MODAL GLASS EFFECT & BLACK BORDERS 👇
        $fixedModalCss = new HtmlString('
            <style>
                /* --- 🌟 MAKE FILAMENT WRAPPERS TRANSPARENT --- */
                html, body, .fi-layout, .fi-main, .fi-page {
                    background-color: transparent !important;
                    background: transparent !important;
                }

                /* --- 🌟 BACKGROUND IMAGE WITH 0.15 OPACITY --- */
                body::before {
                    content: "";
                    position: fixed;
                    top: 0; left: 0; right: 0; bottom: 0;
                    background-image: url("' . $bgImageUrl . '") !important;
                    background-size: cover !important;
                    background-position: center !important;
                    background-attachment: fixed !important;
                    opacity: 0.15 !important;
                    z-index: -999 !important;
                    pointer-events: none;
                }

                /* --- 🎨 MODAL GLASS EFFECT & BLACK BORDER --- */
                .fi-modal-window {
                    background: rgba(255, 255, 255, 0.45) !important;
                    backdrop-filter: blur(16px) saturate(140%) !important;
                    -webkit-backdrop-filter: blur(16px) saturate(140%) !important;
                    border: 1.5px solid #000000 !important; /* BLACK BORDER */
                    box-shadow: 0 8px 32px rgba(42, 71, 149, 0.08) !important;
                    border-radius: 1.25rem !important;
                    display: flex !important; 
                    flex-direction: column !important; 
                    max-height: 85vh !important; 
                    overflow: hidden !important;
                }
                .dark .fi-modal-window {
                    background: rgba(15, 15, 20, 0.7) !important;
                }
                
                .fi-modal-header { 
                    flex-shrink: 0 !important; 
                    border-bottom: 1.5px solid rgba(0,0,0,0.1) !important; 
                    padding-bottom: 1rem !important; 
                    background: rgba(255,255,255,0.2) !important; 
                }
                .dark .fi-modal-header { 
                    border-bottom: 1.5px solid rgba(255,255,255,0.1) !important; 
                    background: rgba(0,0,0,0.2) !important; 
                }
                
                .fi-modal-content { 
                    flex-grow: 1 !important; 
                    overflow-y: auto !important; 
                    padding: 1.5rem !important; 
                }
                
                .fi-modal-footer { 
                    flex-shrink: 0 !important; 
                    border-top: 1.5px solid rgba(0,0,0,0.1) !important; 
                    padding-top: 1rem !important; 
                    margin-top: 0 !important; 
                    background: rgba(255,255,255,0.2) !important; 
                }
                .dark .fi-modal-footer { 
                    border-top: 1.5px solid rgba(255,255,255,0.1) !important; 
                    background: rgba(0,0,0,0.2) !important; 
                }

                /* Inputs in Modal */
                .fi-input-wrapper {
                    border: 1.5px solid #000000 !important; /* BLACK BORDER FOR INPUTS */
                    background: rgba(255,255,255,0.5) !important;
                    border-radius: 0.75rem !important;
                }
                .dark .fi-input-wrapper { background: rgba(0,0,0,0.5) !important; }
                
                .fi-input-wrapper:focus-within {
                    border-color: #f16b3f !important;
                    box-shadow: 0 0 0 3px rgba(241, 107, 63, 0.2) !important;
                }
            </style>
        ');

        return [
            // 0. CSV IMPORT / EXPORT ACTIONS
            Actions\Action::make('downloadSampleCsv')
                ->label('Sample CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->tooltip('Download starter CSV template without image column')
                ->action(function () {
                    $csvContent = \App\Services\MenuImportExportService::generateSampleCsv();
                    return response()->streamDownload(function () use ($csvContent) {
                        echo $csvContent;
                    }, 'menu_import_sample.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),

            Actions\Action::make('importMenuCsv')
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

                    $filePath = \Illuminate\Support\Facades\Storage::disk('local')->path($data['csv_file']);

                    try {
                        $result = \App\Services\MenuImportExportService::importMenuFromCsv($restaurant, $filePath);

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
                        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($data['csv_file'])) {
                            \Illuminate\Support\Facades\Storage::disk('local')->delete($data['csv_file']);
                        }
                    }
                }),

            Actions\Action::make('exportMenuCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->tooltip('Export all active menu items and categories to CSV')
                ->action(function () {
                    $user = auth()->user();
                    $restaurant = $user->restaurant;

                    if (!$restaurant) {
                        Notification::make()->title('Restaurant Not Found')->danger()->send();
                        return;
                    }

                    $csvContent = \App\Services\MenuImportExportService::exportMenuToCsv($restaurant);
                    $filename = Str::slug($restaurant->name) . '_menu_' . now()->format('Y-m-d') . '.csv';

                    return response()->streamDownload(function () use ($csvContent) {
                        echo $csvContent;
                    }, $filename, [
                        'Content-Type' => 'text/csv',
                    ]);
                }),

            // 0. MANAGE CATEGORIES — same Repeater/modal style as "Add Categories", no separate blade/widget needed.
            // Pre-fills with existing categories; toggle = active/inactive, trash icon = delete, edit name inline.
            Actions\Action::make('manageCategories')
                ->label('Manage Categories')
                ->modalHeading('Manage Categories')
                ->modalDescription($fixedModalCss)
                ->extraAttributes(['class' => 'hidden-manage-category hidden'])
                ->fillForm(function () {
                    $user = auth()->user();
                    $categories = Category::where('restaurant_id', $user->restaurant_id)
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get();

                    return [
                        'categories' => $categories->map(function ($cat) {
                            return [
                                'id' => $cat->id,
                                'name' => $cat->name,
                                'is_active' => (bool) $cat->is_active,
                            ];
                        })->toArray(),
                    ];
                })
                ->form([
                    Forms\Components\Repeater::make('categories')
                        ->label('Your Categories')
                        ->addActionLabel('+ Add New Category')
                        ->schema([
                            Forms\Components\Hidden::make('id'),

                            Forms\Components\TextInput::make('name')
                                ->label('Category Name')
                                ->required()
                                ->maxLength(100),

                            Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ])
                        ->columns(2)
                        ->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
                        ->deletable(true)
                        ->addable(true)
                        ->reorderable(true),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    $submittedIds = [];

                    foreach ($data['categories'] as $index => $catData) {
                        if (!empty($catData['id'])) {
                            $submittedIds[] = $catData['id'];
                            $category = Category::where('restaurant_id', $user->restaurant_id)->find($catData['id']);
                            if ($category) {
                                $category->update([
                                    'name' => $catData['name'] ?? $category->name,
                                    'is_active' => $catData['is_active'] ?? $category->is_active,
                                    'sort_order' => $index,
                                ]);
                            }
                        } else {
                            $newCat = Category::create([
                                'restaurant_id' => $user->restaurant_id,
                                'name' => $catData['name'] ?? '',
                                'is_active' => $catData['is_active'] ?? true,
                                'sort_order' => $index,
                            ]);
                            $submittedIds[] = $newCat->id;
                        }
                    }

                    $existingIds = Category::where('restaurant_id', $user->restaurant_id)->pluck('id')->toArray();
                    $toDelete = array_diff($existingIds, $submittedIds);
                    if (!empty($toDelete)) {
                        Category::whereIn('id', $toDelete)->delete();
                    }

                    Notification::make()->title('Categories updated successfully')->success()->send();
                }),

            // 1. BULK ADD CATEGORY ACTION
            Actions\Action::make('addCategory')
                ->label('Add Categories')
                ->modalHeading('Add New Categories')
                ->modalDescription($fixedModalCss)
                ->extraAttributes(['class' => 'hidden-add-category hidden'])
                ->form([
                    Forms\Components\Repeater::make('categories')
                        ->label('Categories to Add')
                        ->addActionLabel('Add Another Category')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Category Name')
                                ->required()
                                ->maxLength(100),

                            Forms\Components\Toggle::make('is_active')
                                ->label('Active by Default')
                                ->default(true),
                        ])
                        ->columns(2)
                        ->itemLabel(fn(array $state): ?string => $state['name'] ?? null),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    $categoriesAdded = 0;

                    foreach ($data['categories'] as $catData) {
                        Category::create([
                            'restaurant_id' => $user->restaurant_id,
                            'name' => $catData['name'],
                            'is_active' => $catData['is_active'],
                        ]);
                        $categoriesAdded++;
                    }

                    Notification::make()->title("{$categoriesAdded} Categor(ies) Added Successfully")->success()->send();
                }),

            // 2. BULK ADD ITEM ACTION
            Actions\Action::make('addItem')
                ->label('Add Items')
                ->modalHeading('Add Items to Category')
                ->modalWidth('5xl')
                ->modalDescription($fixedModalCss)
                ->extraAttributes(['class' => 'hidden-add-item hidden'])
                ->form([
                    Forms\Components\Select::make('category_id')
                        ->label('Target Category')
                        ->required()
                        ->options(function () {
                            $user = auth()->user();
                            return Category::where('restaurant_id', $user->restaurant_id)
                                ->where('is_active', true)
                                ->orderBy('sort_order')
                                ->orderBy('name')
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->columnSpanFull()
                        ->helperText('Select the category these items belong to.'),

                    Forms\Components\Repeater::make('items')
                        ->label('Menu Items')
                        ->addActionLabel('+ Add Another Item')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->schema([
                            Forms\Components\Grid::make(['default' => 1, 'md' => 12])
                                ->schema([
                                    // ── Left Side: Core Details ──
                                    Forms\Components\Group::make([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Item Name')
                                            ->required()
                                            ->maxLength(150)
                                            ->live(onBlur: true),

                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('price')
                                                ->label('Price')
                                                ->numeric()
                                                ->minValue(0)
                                                ->required()
                                                ->prefix('₹'),

                                            Forms\Components\Select::make('is_veg')
                                                ->label('Food Type')
                                                ->options([
                                                    1 => '🟢 Veg',
                                                    0 => '🔴 Non-Veg',
                                                ])
                                                ->default(1)
                                                ->required(),
                                        ]),

                                        Forms\Components\Select::make('item_type')
                                            ->label('Preparation Type')
                                            ->options([
                                                'prepared'  => '🍳 Prepared (Cooked to order)',
                                                'readymade' => '📦 Readymade (Packaged / Bottled / Counter)',
                                            ])
                                            ->default('prepared')
                                            ->required()
                                            ->helperText('Readymade items auto-mark ready and bypass kitchen queues.'),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Description')
                                            ->maxLength(500)
                                            ->rows(2),

                                        Forms\Components\Toggle::make('is_available')
                                            ->default(true)
                                            ->label('Available'),
                                    ])->columnSpan(['default' => 12, 'md' => 7]),

                                    // ── Right Side: Photo Selection ──
                                    Forms\Components\Section::make('Photo')
                                        ->description('Pick a suggestion or upload custom.')
                                        ->schema([
                                            Forms\Components\Radio::make('suggested_image_pick')
                                                ->label('Suggested Photos (Click to Pick)')
                                                ->visible(function (Forms\Get $get) {
                                                    $name = trim((string) ($get('name') ?? ''));
                                                    return strlen($name) >= 2 && !empty(\App\Services\MenuImageService::getMatchingSuggestions($name, limit: 1));
                                                })
                                                ->options(function (Forms\Get $get) {
                                                    $name = trim((string) ($get('name') ?? ''));
                                                    if (strlen($name) < 2) {
                                                        return [];
                                                    }
                                                    $suggestions = \App\Services\MenuImageService::getMatchingSuggestions($name, limit: 6);
                                                    $options = [];
                                                    foreach ($suggestions as $s) {
                                                        $options[$s['path']] = new \Illuminate\Support\HtmlString('
                                                            <div style="display:flex; flex-direction:column; align-items:center; gap:2px; padding:2px;">
                                                                <img src="' . $s['url'] . '" style="width:58px; height:58px; border-radius:8px; object-fit:cover; border:1.5px solid #000; box-shadow:0 2px 6px rgba(0,0,0,0.1);" />
                                                                <span style="font-size:10px; font-weight:600; text-align:center; max-width:68px; line-height:1.2; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . e($s['label']) . '</span>
                                                            </div>
                                                        ');
                                                    }
                                                    return $options;
                                                })
                                                ->columns(3)
                                                ->live()
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    if ($state) {
                                                        $set('image_path', $state);
                                                        $set('image_source', 'global');
                                                        $set('custom_image_upload', null);
                                                    }
                                                })
                                                ->dehydrated(false)
                                                ->columnSpanFull(),

                                            Forms\Components\FileUpload::make('custom_image_upload')
                                                ->label('Or Upload Custom Photo')
                                                ->image()
                                                ->disk('public')
                                                ->directory(function (callable $get) {
                                                    $user = auth()->user();
                                                    $restaurantSlug = Str::slug($user->restaurant->name ?? 'restaurant');

                                                    $categoryId = $get('../../category_id');
                                                    $categoryName = Category::find($categoryId)?->name ?? 'uncategorized';
                                                    $categorySlug = Str::slug($categoryName);

                                                    return "restaurants/{$restaurantSlug}/Categories/{$categorySlug}";
                                                })
                                                ->getUploadedFileNameForStorageUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file, callable $get): string {
                                                    $itemName = Str::slug($get('name') ?? 'item');
                                                    $extension = $file->getClientOriginalExtension();
                                                    return "{$itemName}.{$extension}";
                                                })
                                                ->imageEditor()
                                                ->maxSize(2048)
                                                ->live()
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    if ($state) {
                                                        $set('suggested_image_pick', null);
                                                        $set('image_source', 'custom');
                                                    }
                                                })
                                                ->columnSpanFull(),

                                            Forms\Components\Hidden::make('image_path'),
                                            Forms\Components\Hidden::make('image_source')->default('none'),
                                        ])->columnSpan(['default' => 12, 'md' => 5]),
                                ]),
                        ])
                        ->itemLabel(fn(array $state): ?string => $state['name'] ?? null),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    $restaurant = $user->restaurant;
                    $restaurantSlug = Str::slug($restaurant->name ?? 'restaurant');
                    $categoryId = $data['category_id'];
                    $category = Category::find($categoryId);
                    $categorySlug = $category ? Str::slug($category->name) : 'general';
                    $itemsAdded = 0;

                    foreach ($data['items'] as $item) {
                        $imagePath = null;
                        $imageSource = 'none';

                        // 1. Process custom uploaded file
                        if (!empty($item['custom_image_upload'])) {
                            $upload = is_array($item['custom_image_upload']) 
                                ? reset($item['custom_image_upload']) 
                                : $item['custom_image_upload'];

                            if ($upload instanceof \Illuminate\Http\UploadedFile) {
                                $ext = $upload->getClientOriginalExtension() ?: 'jpg';
                                $itemSlug = Str::slug($item['name'] ?? 'item');
                                $dir = "restaurants/{$restaurantSlug}/Categories/{$categorySlug}";
                                $filename = "{$itemSlug}.{$ext}";
                                $storedPath = \Illuminate\Support\Facades\Storage::disk('public')->putFileAs($dir, $upload, $filename);
                                $imagePath = $storedPath;
                                $imageSource = 'custom';
                            } elseif (is_string($upload) && !str_contains($upload, 'AppData') && !str_ends_with($upload, '.tmp')) {
                                $imagePath = $upload;
                                $imageSource = 'custom';
                            }
                        }
                        // 2. Process suggested global image pick
                        elseif (!empty($item['suggested_image_pick'])) {
                            $imagePath = $item['suggested_image_pick'];
                            $imageSource = 'global';
                        }
                        // 3. Fallback to existing path if valid
                        elseif (!empty($item['image_path']) && !str_contains($item['image_path'], 'AppData') && !str_ends_with($item['image_path'], '.tmp')) {
                            $imagePath = $item['image_path'];
                            $imageSource = $item['image_source'] ?? 'none';
                        }

                        MenuItem::create([
                            'restaurant_id' => $user->restaurant_id,
                            'category_id'   => $categoryId,
                            'name'          => $item['name'],
                            'price'         => $item['price'],
                            'item_type'     => $item['item_type'] ?? 'prepared',
                            'image_source'  => $imageSource,
                            'image_path'    => $imagePath,
                            'is_veg'        => (bool) ($item['is_veg'] ?? true),
                            'description'   => $item['description'] ?? null,
                            'is_available'  => $item['is_available'] ?? true,
                        ]);
                        $itemsAdded++;
                    }

                    Notification::make()->title("{$itemsAdded} Item(s) Added")->success()->send();
                }),
        ];
    }
}