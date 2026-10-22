<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuResource\Pages;
use App\Models\MenuItem;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class MenuResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Menu Dashboard';
    protected static ?string $navigationGroup = 'Menu Management';
    protected static ?int $navigationSort = 1;

    /* -----------------------------------------------------------
       PERMISSIONS
    ------------------------------------------------------------*/
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->is_super_admin) return true;
        return (bool) $user->restaurant_id
            && in_array($user->role?->name, ['restaurant_admin', 'manager', 'owner']);
    }

    public static function canCreate(): bool
    {
        return true; 
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return true; 
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return true;
    }

    /* -----------------------------------------------------------
       DATA ISOLATION
    ------------------------------------------------------------*/
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()->withoutGlobalScopes();
        
        if ($user && $user->restaurant_id) {
            $query->where('restaurant_id', $user->restaurant_id);
        }

        return $query;
    }

    /* -----------------------------------------------------------
       CARD GRID LAYOUT (GLASS + BLACK BORDER)
    ------------------------------------------------------------*/
    public static function table(Table $table): Table
    {
        $bgImageUrl = asset('images/bg.png');

        return $table
            ->heading(new HtmlString('
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

                    /* --- 🎨 CARD BASE (GLASS + BLACK BORDER) --- */
                    .fi-ta-record {
                        border-radius: 16px !important;
                        background: rgba(255, 255, 255, 0.45) !important;
                        backdrop-filter: blur(16px) saturate(140%) !important;
                        -webkit-backdrop-filter: blur(16px) saturate(140%) !important;
                        border: 1.5px solid #000000 !important; /* BLACK BORDER */
                        box-shadow: 0 8px 32px rgba(42, 71, 149, 0.08) !important;
                        transition: all 0.3s ease !important;
                        overflow: hidden !important;
                        position: relative !important;
                    }
                    .dark .fi-ta-record { background: rgba(15, 15, 20, 0.7) !important; }
                    
                    .fi-ta-record:hover {
                        transform: translateY(-5px) !important;
                        box-shadow: 0 12px 40px rgba(42, 71, 149, 0.15) !important;
                    }

                    /* Category Badge */
                    .fi-ta-record .fi-badge {
                        color: #2a4795 !important; 
                        background-color: rgba(42, 71, 149, 0.1) !important;
                        border: 1px solid rgba(42, 71, 149, 0.2) !important;
                    }

                    /* Veg/Non-Veg Badges */
                    .type-badge-veg {
                        display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 99px;
                        font-size: 0.65rem; font-weight: 800; background: rgba(34, 197, 94, 0.15); color: #16a34a; border: 1px solid rgba(34, 197, 94, 0.3);
                        margin-top: 4px;
                    }
                    .type-badge-non-veg {
                        display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 99px;
                        font-size: 0.65rem; font-weight: 800; background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3);
                        margin-top: 4px;
                    }

                    /* Price & Toggle Switch */
                    .custom-price-text { color: #2a4795 !important; font-size: 1.1rem !important; }
                    .fi-ta-record button[role="switch"][aria-checked="true"] { background-color: #f16b3f !important; }

                    /* Edit Button (Blue Style) */
                    .fi-ta-record .fi-ta-actions button:nth-of-type(1) {
                        background-color: #2a4795 !important; border: 1px solid #000000 !important; color: white !important; border-radius: 8px !important; padding: 4px 12px !important;
                    }
                    .fi-ta-record .fi-ta-actions button:nth-of-type(1):hover { background-color: #456aba !important; }

                    /* Delete Button (Red Style) */
                    .fi-ta-record .fi-ta-actions button:nth-of-type(2) {
                        color: #ef4444 !important; border: 1px solid #ef4444 !important; border-radius: 8px !important; background: transparent !important;
                    }
                    .fi-ta-record .fi-ta-actions button:nth-of-type(2):hover { background: rgba(239, 68, 68, 0.1) !important; }

                    .inherited-badge {
                        position: absolute; top: 10px; right: 10px; background: #2a4795; color: white; font-size: 0.62rem; font-weight: 800; padding: 2px 8px; border-radius: 99px; z-index: 20; border: 1.5px solid #000000; letter-spacing: 0.02em; box-shadow: 0 2px 6px rgba(0,0,0,0.15);
                    }
                </style>
            '))
            ->contentGrid([
                'md' => 3, 
                'lg' => 4, 
                'xl' => 5, 
            ])
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\ImageColumn::make('image_path')
                        ->height('140px') 
                        ->width('100%')
                        ->defaultImageUrl('https://ui-avatars.com/api/?name=Food&color=FFFFFF&background=111827')
                        ->extraImgAttributes(['class' => 'object-cover w-full h-full border-b border-black']),

                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('name')
                            ->weight('black')
                            ->size('lg')
                            ->searchable(), 

                        Tables\Columns\TextColumn::make('type')
                            ->formatStateUsing(function (?string $state, $record): HtmlString {
                                $isVeg = ($state === 'veg' || $record->is_veg);
                                $typeHtml = $isVeg 
                                    ? '<span class="type-badge-veg">● VEG</span>' 
                                    : '<span class="type-badge-non-veg">● NON-VEG</span>';

                                $itemTypeHtml = ($record->item_type === 'readymade')
                                    ? '<span style="display: inline-flex; align-items: center; gap: 3px; padding: 2px 7px; border-radius: 99px; font-size: 0.62rem; font-weight: 800; background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(16, 185, 129, 0.35);">📦 READYMADE</span>'
                                    : '<span style="display: inline-flex; align-items: center; gap: 3px; padding: 2px 7px; border-radius: 99px; font-size: 0.62rem; font-weight: 800; background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.35);">🍳 PREPARED</span>';

                                $imgSourceBadge = match ($record->image_source) {
                                    'global' => '<span style="display: inline-flex; align-items: center; gap: 2px; padding: 2px 6px; border-radius: 99px; font-size: 0.60rem; font-weight: 700; background: rgba(59, 130, 246, 0.12); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.3);">🌐 Global Pic</span>',
                                    'custom' => '<span style="display: inline-flex; align-items: center; gap: 2px; padding: 2px 6px; border-radius: 99px; font-size: 0.60rem; font-weight: 700; background: rgba(147, 51, 234, 0.12); color: #7c3aed; border: 1px solid rgba(147, 51, 234, 0.3);">📷 Custom Pic</span>',
                                    default => '<span style="display: inline-flex; align-items: center; gap: 2px; padding: 2px 6px; border-radius: 99px; font-size: 0.60rem; font-weight: 700; background: rgba(107, 114, 128, 0.12); color: #4b5563; border: 1px solid rgba(107, 114, 128, 0.25);">⚪ No Pic</span>',
                                };

                                return new HtmlString('<div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 4px;">' . $typeHtml . $itemTypeHtml . $imgSourceBadge . '</div>');
                            }),

                        Tables\Columns\TextColumn::make('category.name')
                            ->badge()
                            ->size('xs'), 

                        Tables\Columns\TextColumn::make('price')
                            ->money('INR')
                            ->weight('black')
                            ->extraAttributes(['class' => 'custom-price-text']), 

                        Tables\Columns\ToggleColumn::make('is_available')
                            ->label('Stock Status')
                            ->getStateUsing(fn($record) => (bool) $record?->is_available)
                            ->updateStateUsing(fn($record, $state) => $record?->update(['is_available' => $state])),
                    ])->space(2)->extraAttributes(['class' => 'p-4']), 
                ])->space(0),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Filter by Category')
                    ->options(fn() => Category::where('restaurant_id', auth()->user()?->restaurant_id)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable(),
                
                Tables\Filters\SelectFilter::make('type')
                    ->label('Filter by Type')
                    ->options(['veg' => 'Veg', 'non-veg' => 'Non-Veg']),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_recipe')
                    ->label('Recipe')
                    ->icon('heroicon-o-beaker')
                    ->button()
                    ->size('xs')
                    ->color('info')
                    ->visible(fn() => (bool) auth()->user()->restaurant?->has_detailed_inventory)
                    ->modalHeading(fn($record) => "Recipe for: {$record->name}")
                    ->modalWidth('3xl')
                    ->form(fn($record) => [
                        Forms\Components\Repeater::make('ingredients')
                            ->label('Ingredients Required (per 1 portion)')
                            ->schema([
                                Forms\Components\Select::make('grocery_item_id')
                                    ->label('Raw Material')
                                    ->options(fn() => \App\Models\GroceryItem::where('restaurant_id', auth()->user()->restaurant_id)->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantity_required')
                                    ->label('Qty Needed')
                                    ->numeric()
                                    ->minValue(0.0001)
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('measurement_unit_id')
                                    ->label('Unit')
                                    ->options(fn() => \App\Models\MeasurementUnit::where('restaurant_id', auth()->user()->restaurant_id)->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(1),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('+ Add Ingredient')
                            ->default(
                                $record->recipes()->with('groceryItem', 'measurementUnit')->get()->map(fn($r) => [
                                    'grocery_item_id' => $r->grocery_item_id,
                                    'quantity_required' => $r->quantity_required,
                                    'measurement_unit_id' => $r->measurement_unit_id,
                                ])->toArray()
                            ),
                    ])
                    ->action(function ($record, array $data) {
                        $record->recipes()->delete();

                        foreach ($data['ingredients'] ?? [] as $ingredient) {
                            $record->recipes()->create([
                                'grocery_item_id' => $ingredient['grocery_item_id'],
                                'quantity_required' => $ingredient['quantity_required'],
                                'measurement_unit_id' => $ingredient['measurement_unit_id'],
                            ]);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Recipe updated for ' . $record->name)
                            ->body(count($data['ingredients'] ?? []) . ' ingredient(s) saved.')
                            ->success()->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->button()
                    ->size('xs')
                    ->modalWidth('4xl')
                    ->modalHeading(fn($record) => "Edit Item: {$record->name}")
                    ->form([
                        Forms\Components\Grid::make(['default' => 1, 'md' => 12])
                            ->schema([
                                // ── Left Column: Basic Information (7 cols) ──
                                Forms\Components\Group::make([
                                    Forms\Components\Select::make('category_id')
                                        ->label('Category')
                                        ->required()
                                        ->options(function () {
                                            $user = auth()->user();
                                            return Category::where('restaurant_id', $user->restaurant_id)
                                                ->orderBy('sort_order')
                                                ->orderBy('name')
                                                ->pluck('name', 'id');
                                        })
                                        ->searchable()
                                        ->preload(),

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
                                        ->helperText('Readymade items auto-mark ready and bypass kitchen wait.'),

                                    Forms\Components\Textarea::make('description')
                                        ->label('Description')
                                        ->rows(2)
                                        ->maxLength(500),

                                    Forms\Components\Toggle::make('is_available')
                                        ->label('In Stock & Available to Order')
                                        ->default(true),
                                ])->columnSpan(['default' => 12, 'md' => 7]),

                                // ── Right Column: Photo Management (5 cols) ──
                                Forms\Components\Section::make('Item Photo')
                                    ->description('Pick a suggestion or upload custom.')
                                    ->schema([
                                        Forms\Components\Placeholder::make('active_image_preview')
                                            ->label('Current Photo')
                                            ->content(function (Forms\Get $get, $record) {
                                                $customUpload = $get('custom_image_upload');
                                                if (!empty($customUpload)) {
                                                    $upload = is_array($customUpload) ? reset($customUpload) : $customUpload;
                                                    if ($upload instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                                        try {
                                                            $url = $upload->temporaryUrl();
                                                            return new \Illuminate\Support\HtmlString('
                                                                <div style="display:flex; align-items:center; gap:10px;">
                                                                    <img src="' . $url . '" style="width:64px; height:64px; border-radius:10px; object-fit:cover; border:2px solid #7c3aed; box-shadow:0 2px 8px rgba(124,58,237,0.25);" />
                                                                    <div>
                                                                        <span class="text-xs text-purple-600 font-bold block">📷 Custom Photo Ready</span>
                                                                        <span class="text-xs text-gray-400">Will save on submit</span>
                                                                    </div>
                                                                </div>
                                                            ');
                                                        } catch (\Throwable $e) {}
                                                    }
                                                }

                                                $suggestedPick = $get('suggested_image_pick');
                                                if (!empty($suggestedPick)) {
                                                    $url = asset('storage/' . $suggestedPick);
                                                    return new \Illuminate\Support\HtmlString('
                                                        <div style="display:flex; align-items:center; gap:10px;">
                                                            <img src="' . $url . '" style="width:64px; height:64px; border-radius:10px; object-fit:cover; border:2px solid #2563eb; box-shadow:0 2px 8px rgba(37,99,235,0.25);" />
                                                            <div>
                                                                <span class="text-xs text-blue-600 font-bold block">🌐 Suggested Photo Picked</span>
                                                                <span class="text-xs text-gray-400">' . e(basename($suggestedPick)) . '</span>
                                                            </div>
                                                        </div>
                                                    ');
                                                }

                                                $path = $get('image_path') ?? $record?->image_path;
                                                if (!$path || str_contains($path, 'AppData') || str_ends_with($path, '.tmp')) {
                                                    return new \Illuminate\Support\HtmlString('<span class="text-xs text-gray-400 italic">No photo assigned. Pick a suggestion or upload custom.</span>');
                                                }

                                                $url = \Illuminate\Support\Str::startsWith($path, ['http://', 'https://']) ? $path : asset('storage/' . $path);
                                                return new \Illuminate\Support\HtmlString('
                                                    <div style="display:flex; align-items:center; gap:10px;">
                                                        <img src="' . $url . '" style="width:64px; height:64px; border-radius:10px; object-fit:cover; border:2px solid #059669; box-shadow:0 2px 8px rgba(5,150,105,0.25);" />
                                                        <div>
                                                            <span class="text-xs text-emerald-600 font-bold block">✓ Photo Active</span>
                                                            <span class="text-xs text-gray-400">' . e(basename($path)) . '</span>
                                                        </div>
                                                    </div>
                                                ');
                                            }),

                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('remove_photo')
                                                ->label('✕ Remove Photo')
                                                ->color('danger')
                                                ->size('xs')
                                                ->visible(fn(Forms\Get $get) => !empty($get('image_path')) || !empty($get('suggested_image_pick')) || !empty($get('custom_image_upload')))
                                                ->action(function (callable $set) {
                                                    $set('image_path', null);
                                                    $set('image_source', 'none');
                                                    $set('suggested_image_pick', null);
                                                    $set('custom_image_upload', null);
                                                }),
                                        ])->columnSpanFull(),

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
                                                $restaurantSlug = Str::slug($user->restaurant->name ?? 'default');
                                                $categoryId = $get('category_id');
                                                $category = Category::find($categoryId);
                                                $categorySlug = $category ? Str::slug($category->name) : 'general';
                                                return "restaurants/{$restaurantSlug}/Categories/{$categorySlug}";
                                            })
                                            ->getUploadedFileNameForStorageUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file, callable $get): string {
                                                $itemName = $get('name');
                                                $cleanName = $itemName ? Str::slug($itemName) : 'item';
                                                $extension = $file->getClientOriginalExtension();
                                                return "{$cleanName}." . $extension;
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
                    ->mutateFormDataBeforeSave(function (array $data, $record): array {
                        $user = auth()->user();
                        $restaurantSlug = Str::slug($user->restaurant->name ?? 'default');
                        $category = Category::find($data['category_id'] ?? $record->category_id);
                        $categorySlug = $category ? Str::slug($category->name) : 'general';
                        $itemName = $data['name'] ?? $record->name;
                        $itemSlug = Str::slug($itemName);

                        // 1. Process custom image upload
                        if (!empty($data['custom_image_upload'])) {
                            $upload = is_array($data['custom_image_upload']) 
                                ? reset($data['custom_image_upload']) 
                                : $data['custom_image_upload'];

                            if ($upload instanceof \Illuminate\Http\UploadedFile) {
                                $ext = $upload->getClientOriginalExtension() ?: 'jpg';
                                $dir = "restaurants/{$restaurantSlug}/Categories/{$categorySlug}";
                                $filename = "{$itemSlug}.{$ext}";
                                $storedPath = \Illuminate\Support\Facades\Storage::disk('public')->putFileAs($dir, $upload, $filename);
                                $data['image_path'] = $storedPath;
                                $data['image_source'] = 'custom';
                            } elseif (is_string($upload) && !str_contains($upload, 'AppData') && !str_ends_with($upload, '.tmp')) {
                                $data['image_path'] = $upload;
                                $data['image_source'] = 'custom';
                            }
                        }
                        // 2. Process suggested global image pick
                        elseif (!empty($data['suggested_image_pick'])) {
                            $data['image_path'] = $data['suggested_image_pick'];
                            $data['image_source'] = 'global';
                        }
                        // 3. Process photo removal
                        elseif (($data['image_source'] ?? '') === 'none') {
                            $data['image_path'] = null;
                            $data['image_source'] = 'none';
                        }

                        unset($data['custom_image_upload'], $data['suggested_image_pick']);
                        return $data;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->size('xs')
                    ->outlined(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMenus::route('/'),
        ];
    }
}