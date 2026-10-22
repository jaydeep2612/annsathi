<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuItem;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB; // 👈 Import DB for Toggle Logic

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Menu Items';
    protected static ?string $navigationGroup = 'Menu Management';
    protected static ?int $navigationSort = 2;
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return !auth()->user()->isManager();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return true; 
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return !auth()->user()->isManager();
    }

    /* -----------------------------------------------------------
       DATA ISOLATION (FIXED)
    ------------------------------------------------------------*/
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->where('restaurant_id', $user->restaurant_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('restaurant_id')
                ->default(fn() => auth()->user()->restaurant_id)
                ->required(),

            Forms\Components\Select::make('category_id')
                ->label('Category')
                ->required()
                ->options(function () {
                    $user = auth()->user();
                    return Category::withoutGlobalScopes()
                        ->where('restaurant_id', $user->restaurant_id)
                        ->where('is_active', true)
                        ->pluck('name', 'id');
                })
                ->searchable(),

            Forms\Components\TextInput::make('name')
                ->label('Item Name')
                ->required()
                ->maxLength(150)
                ->live(onBlur: true),

            Forms\Components\Textarea::make('description')
                ->maxLength(500)
                ->columnSpanFull(),

            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('price')
                    ->label('Price (₹)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                Forms\Components\Select::make('item_type')
                    ->label('Preparation Type')
                    ->options([
                        'prepared'  => '🍳 Prepared (Cooked to order)',
                        'readymade' => '📦 Readymade (Packaged / Bottled / Counter)',
                    ])
                    ->default('prepared')
                    ->required()
                    ->helperText('Readymade items automatically mark ready on placement and bypass kitchen cooking queues.'),

                Forms\Components\Toggle::make('is_veg')
                    ->label('Vegetarian (Veg)')
                    ->default(true)
                    ->inline(false),
            ]),

            Forms\Components\Section::make('Item Photo')
                ->description('Click a matching suggested photo or upload your own.')
                ->schema([
                    Forms\Components\Placeholder::make('active_image_preview')
                        ->label('Current Photo')
                        ->content(function (Forms\Get $get, $record) {
                            $path = $get('image_path') ?? $record?->image_path;
                            if (!$path) {
                                return new \Illuminate\Support\HtmlString('<span class="text-xs text-gray-400 italic">No photo assigned. Type an item name above to see photo suggestions, or upload one below.</span>');
                            }
                            $url = \Illuminate\Support\Str::startsWith($path, ['http://', 'https://']) ? $path : asset('storage/' . $path);
                            return new \Illuminate\Support\HtmlString('
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <img src="' . $url . '" style="width:72px; height:72px; border-radius:12px; object-fit:cover; border:2px solid #2a4795; box-shadow:0 4px 12px rgba(42,71,149,0.18);" />
                                    <span class="text-xs text-emerald-600 font-semibold">✓ Photo Selected</span>
                                </div>
                            ');
                        }),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('remove_photo')
                            ->label('✕ Remove Photo')
                            ->color('danger')
                            ->size('xs')
                            ->visible(fn(Forms\Get $get) => !empty($get('image_path')))
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
                                    <div style="display:flex; flex-direction:column; align-items:center; gap:4px; padding:4px;">
                                        <img src="' . $s['url'] . '" style="width:68px; height:68px; border-radius:10px; object-fit:cover; border:1.5px solid #000; box-shadow:0 2px 6px rgba(0,0,0,0.1);" />
                                        <span style="font-size:11px; font-weight:600; text-align:center; max-width:76px; line-height:1.2; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . e($s['label']) . '</span>
                                    </div>
                                ');
                            }
                            return $options;
                        })
                        ->columns(['default' => 3, 'sm' => 4, 'md' => 6])
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
                            $restaurantSlug = $user->restaurant->slug ?? 'default';
                            $categoryId = $get('category_id');
                            $category = Category::find($categoryId);
                            $categorySlug = $category ? Str::slug($category->name) : 'general';
                            return "restaurants/{$restaurantSlug}/Categories/{$categorySlug}";
                        })
                        ->getUploadedFileNameForStorageUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file, callable $get): string {
                            $itemName = $get('name');
                            $cleanName = $itemName ? \Illuminate\Support\Str::slug($itemName) : 'item';
                            $extension = $file->getClientOriginalExtension();
                            return "{$cleanName}." . $extension;
                        })
                        ->imageEditor()
                        ->maxSize(2048)
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $set('image_path', $state);
                                $set('image_source', 'custom');
                                $set('suggested_image_pick', null);
                            }
                        })
                        ->dehydrated(false)
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('image_path'),
                    Forms\Components\Hidden::make('image_source')->default('none'),
                ]),

            Forms\Components\Toggle::make('is_available')
                ->label('Available for Ordering')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading(new HtmlString('
                <style>
                    .fi-ta-ctn { background-color: transparent !important; box-shadow: none !important; border: 1px solid rgba(156, 163, 175, 0.2) !important; }
                    .fi-ta-header-toolbar, .fi-ta-footer, .fi-ta-content, .fi-ta-table thead, .fi-ta-table th { background-color: transparent !important; border-color: rgba(156, 163, 175, 0.2) !important; }
                    .fi-ta-record { background-color: transparent !important; border-bottom: 1px solid rgba(156, 163, 175, 0.2) !important; transition: background-color 0.2s ease; }
                    .fi-ta-record:hover { background-color: rgba(234, 88, 12, 0.05) !important; }
                </style>
                <span style="font-size: 1.25rem; font-weight: 800;">Restaurant Menu Items</span>
            '))
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('IMAGE')
                    ->circular()
                    ->size(50)
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=Food&color=FFFFFF&background=111827'),

                Tables\Columns\TextColumn::make('name')
                    ->label('ITEM NAME')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->description(fn(MenuItem $record): string => Str::limit($record->description ?? '', 40)),

                Tables\Columns\TextColumn::make('item_type')
                    ->label('TYPE')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'readymade' => '📦 Readymade',
                        default => '🍳 Prepared',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'readymade' => 'success',
                        default => 'warning',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('image_source')
                    ->label('IMG SOURCE')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'global' => '🌐 Global',
                        'custom' => '🏢 Custom',
                        default => '⚪ Blank',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'global' => 'info',
                        'custom' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('CATEGORY')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Appetizers' => 'info',
                        'Main Course' => 'warning',
                        'Desserts' => 'danger',
                        'Beverages' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->label('PRICE')
                    ->money('INR')
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\ToggleColumn::make('is_available')
                    ->label('AVAILABILITY')
                    ->onColor('warning'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->color('warning')
                    ->button()
                    ->outlined()
                    ->visible(fn() => !auth()->user()->isManager()),

                Tables\Actions\DeleteAction::make()
                    ->color('danger')
                    ->button()
                    ->outlined()
                    ->visible(fn() => !auth()->user()->isManager()),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn() => !auth()->user()->isManager()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}