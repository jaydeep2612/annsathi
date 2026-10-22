<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RestaurantResource\Pages;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

class RestaurantResource extends Resource
{
    protected static ?string $model = Restaurant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'Restaurants';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->is_super_admin || $user->isOwner();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && !$user->is_super_admin && $user->isOwner()) {
            $query->where('owner_id', $user->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Wizard::make([
                // ── STEP 1: IDENTITY & ADMIN SETUP ────────────────────────────
                Forms\Components\Wizard\Step::make('Identity & Staff')
                    ->description('Basic restaurant details and administrator')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        Forms\Components\Select::make('owner_id')
                            ->label('Assigned Owner')
                            ->relationship('owner', 'name', fn(Builder $query) => $query->whereHas('role', fn($r) => $r->where('name', 'owner')))
                            ->searchable()
                            ->preload()
                            ->helperText('Multiple restaurants can be assigned to the same owner.')
                            ->visible(fn() => auth()->user()?->is_super_admin),

                        Forms\Components\TextInput::make('name')
                            ->label('Restaurant Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn($state, callable $set) =>
                                $set('slug', Str::slug($state))
                            ),

                        Forms\Components\TextInput::make('slug')
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Restaurant Logo')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory(
                                fn($get) =>
                                'restaurants/' . ($get('slug') ?? 'temp') . '/LOGO'
                            )
                            ->getUploadedFileNameForStorageUsing(
                                fn($file) => 'logo.' . $file->getClientOriginalExtension()
                            )
                            ->visibility('public')
                            ->maxSize(2048),

                        Forms\Components\Textarea::make('address')
                            ->label('Address')
                            ->maxLength(65535)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('phone_no')
                            ->label('Contact Phone')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('gst_no')
                            ->label('GSTIN / Tax ID')
                            ->placeholder('e.g. 24ABCDE1234F1Z5')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('upi_id')
                            ->label('Master UPI ID')
                            ->placeholder('e.g. merchant@okhdfcbank')
                            ->maxLength(255)
                            ->helperText('Used for digital payments and dynamic QR bills.'),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true),

                        Forms\Components\Section::make('Restaurant Admin Credentials')
                            ->description('Login credentials created for the primary store admin.')
                            ->schema([
                                Forms\Components\TextInput::make('admin_name')
                                    ->label('Admin Name')
                                    ->required(fn($livewire) => $livewire instanceof Pages\CreateRestaurant)
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('admin_email')
                                    ->label('Admin Email')
                                    ->email()
                                    ->required(fn($livewire) => $livewire instanceof Pages\CreateRestaurant)
                                    ->unique('users', 'email')
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('admin_password')
                                    ->label('Admin Password')
                                    ->password()
                                    ->required(fn($livewire) => $livewire instanceof Pages\CreateRestaurant)
                                    ->dehydrated(false),
                            ])
                            ->columns(3)
                            ->visible(fn($livewire) => $livewire instanceof Pages\CreateRestaurant),
                    ])->columns(2),

                // ── STEP 2: OPERATIONAL WORKFLOW & ARCHETYPE ─────────────────
                Forms\Components\Wizard\Step::make('Archetype & Workflow')
                    ->description('Order routing, active roles, and channels')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Forms\Components\Select::make('business_type')
                            ->label('Business Archetype')
                            ->options([
                                'cafe'               => 'Cafe & Coffee Shop (Single Operator / Fast Turnaround)',
                                'quick_service'      => 'Fast Food / QSR / Food Truck (Token / High Throughput)',
                                'sweet_shop_outlet'  => 'Sweet Shop / Retail Counter (Direct Billing)',
                                'casual_dining'      => 'Casual Dining (Table QR, Waiter & Chef)',
                                'fine_dining'        => 'Fine Dining / Multi-Floor (Captain, Waiter, Multi-Station KOT)',
                                'cloud_kitchen'      => 'Cloud Kitchen (Kitchen Display & Parcel only)',
                                'hotel_resort'       => 'Hotel & Resort (Room QR Dining + Dine-in)',
                            ])
                            ->default('casual_dining')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                match ($state) {
                                    'cafe' => [
                                        $set('workflow_preset', 'solo_manager'),
                                        $set('active_workflow_roles', ['manager', 'cashier']),
                                        $set('kitchen_order_dispatch', false),
                                        $set('waiter_serving_dispatch', false),
                                        $set('is_pay_first', true),
                                        $set('token_system_enabled', true),
                                        $set('enabled_service_modes', ['dine_in', 'takeaway_parcel']),
                                    ],
                                    'quick_service' => [
                                        $set('workflow_preset', 'custom'),
                                        $set('active_workflow_roles', ['cashier', 'chef']),
                                        $set('kitchen_order_dispatch', true),
                                        $set('waiter_serving_dispatch', false),
                                        $set('is_pay_first', true),
                                        $set('token_system_enabled', true),
                                        $set('enabled_service_modes', ['takeaway_parcel', 'counter_pos']),
                                    ],
                                    'sweet_shop_outlet' => [
                                        $set('workflow_preset', 'solo_manager'),
                                        $set('active_workflow_roles', ['cashier']),
                                        $set('kitchen_order_dispatch', false),
                                        $set('waiter_serving_dispatch', false),
                                        $set('is_pay_first', true),
                                        $set('token_system_enabled', false),
                                        $set('enabled_service_modes', ['counter_pos', 'takeaway_parcel']),
                                    ],
                                    'fine_dining' => [
                                        $set('workflow_preset', 'full_crew'),
                                        $set('active_workflow_roles', ['captain', 'waiter', 'chef', 'manager', 'cashier']),
                                        $set('kitchen_order_dispatch', true),
                                        $set('waiter_serving_dispatch', true),
                                        $set('is_pay_first', false),
                                        $set('token_system_enabled', false),
                                        $set('enabled_service_modes', ['dine_in']),
                                    ],
                                    'cloud_kitchen' => [
                                        $set('workflow_preset', 'custom'),
                                        $set('active_workflow_roles', ['chef', 'manager']),
                                        $set('kitchen_order_dispatch', true),
                                        $set('waiter_serving_dispatch', false),
                                        $set('is_pay_first', true),
                                        $set('token_system_enabled', true),
                                        $set('enabled_service_modes', ['takeaway_parcel']),
                                    ],
                                    'hotel_resort' => [
                                        $set('workflow_preset', 'full_crew'),
                                        $set('active_workflow_roles', ['waiter', 'chef', 'manager', 'cashier']),
                                        $set('kitchen_order_dispatch', true),
                                        $set('waiter_serving_dispatch', true),
                                        $set('is_rooms_facility', true),
                                        $set('rooms_capacity_limit', 30),
                                        $set('enabled_service_modes', ['room_service', 'dine_in']),
                                    ],
                                    default => [
                                        $set('workflow_preset', 'manager_waiter_chef'),
                                        $set('active_workflow_roles', ['manager', 'waiter', 'chef']),
                                        $set('kitchen_order_dispatch', true),
                                        $set('waiter_serving_dispatch', true),
                                        $set('is_pay_first', false),
                                        $set('token_system_enabled', false),
                                        $set('enabled_service_modes', ['dine_in', 'takeaway_parcel']),
                                    ],
                                };
                            })
                            ->dehydrated(false),

                        Forms\Components\Select::make('workflow_preset')
                            ->label('Workflow Preset')
                            ->options([
                                'solo_manager'        => 'Solo Manager (Single person handles order, prep & billing)',
                                'manager_waiter'      => 'Manager + Waiter (Waiters take/serve; Manager oversees)',
                                'manager_waiter_chef' => 'Standard Dining (Chef in kitchen + Waiter + Manager)',
                                'full_crew'           => 'Full Crew (Captain + Waiter + Chef + Cashier + Manager)',
                                'custom'              => 'Custom Dynamic Workflow',
                            ])
                            ->default('manager_waiter_chef')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                match ($state) {
                                    'solo_manager' => [
                                        $set('active_workflow_roles', ['manager', 'cashier']),
                                        $set('kitchen_order_dispatch', false),
                                        $set('waiter_serving_dispatch', false),
                                    ],
                                    'manager_waiter' => [
                                        $set('active_workflow_roles', ['manager', 'waiter', 'cashier']),
                                        $set('kitchen_order_dispatch', false),
                                        $set('waiter_serving_dispatch', true),
                                    ],
                                    'manager_waiter_chef' => [
                                        $set('active_workflow_roles', ['manager', 'waiter', 'chef']),
                                        $set('kitchen_order_dispatch', true),
                                        $set('waiter_serving_dispatch', true),
                                    ],
                                    'full_crew' => [
                                        $set('active_workflow_roles', ['captain', 'waiter', 'chef', 'manager', 'cashier']),
                                        $set('kitchen_order_dispatch', true),
                                        $set('waiter_serving_dispatch', true),
                                    ],
                                    default => null,
                                };
                            })
                            ->dehydrated(false),

                        Forms\Components\CheckboxList::make('active_workflow_roles')
                            ->label('Active Staff Roles in Workflow')
                            ->options([
                                'manager' => 'Manager',
                                'captain' => 'Captain',
                                'waiter'  => 'Waiter',
                                'chef'    => 'Chef / Cook',
                                'cashier' => 'Cashier',
                            ])
                            ->default(['manager', 'waiter', 'chef'])
                            ->columns(3)
                            ->dehydrated(false),

                        Forms\Components\Section::make('Dispatch Rules & Channels')->schema([
                            Forms\Components\Toggle::make('require_order_approval')
                                ->label('Require Manager / Staff Order Approval')
                                ->helperText('When enabled, customer QR orders must be approved by Manager or Waiter before hitting the kitchen.')
                                ->default(true)
                                ->dehydrated(false),

                            Forms\Components\Toggle::make('waiter_can_take_orders')
                                ->label('Allow Waiters to Place Orders')
                                ->helperText('When enabled, waiters can take orders on handhelds. When disabled, waiters can only deliver and serve.')
                                ->default(true)
                                ->dehydrated(false),

                            Forms\Components\Toggle::make('kitchen_order_dispatch')
                                ->label('Kitchen Order Dispatch (KOT)')
                                ->helperText('Dispatches order items to Kitchen Display / Chef Queue.')
                                ->default(true)
                                ->dehydrated(false),

                            Forms\Components\Toggle::make('waiter_serving_dispatch')
                                ->label('Waiter Serving Dispatch')
                                ->helperText('Notifies waiters when food is marked ready by kitchen.')
                                ->default(true)
                                ->dehydrated(false),

                            Forms\Components\Toggle::make('is_pay_first')
                                ->label('Pay-First Ordering (QSR / Cafe Model)')
                                ->helperText('Orders must be settled before kitchen begins preparation.')
                                ->default(false)
                                ->dehydrated(false),

                            Forms\Components\Toggle::make('token_system_enabled')
                                ->label('Daily Token Numbers (#01, #02...)')
                                ->default(false)
                                ->dehydrated(false),

                            Forms\Components\CheckboxList::make('enabled_service_modes')
                                ->label('Enabled Ordering Channels')
                                ->options([
                                    'dine_in'         => 'Table Dine-In (Table QR)',
                                    'takeaway_parcel' => 'Takeaway / Parcel (Parcel QR)',
                                    'room_service'    => 'Room Service (Room QR)',
                                    'counter_pos'     => 'Direct Counter POS',
                                ])
                                ->default(['dine_in', 'takeaway_parcel'])
                                ->columns(2)
                                ->dehydrated(false),
                        ])->columns(2),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('table_capacity_limit')
                                ->label('Table Limit')
                                ->numeric()
                                ->default(20)
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('user_capacity_limit')
                                ->label('Staff User Limit')
                                ->numeric()
                                ->default(10)
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('rooms_capacity_limit')
                                ->label('Rooms Limit')
                                ->numeric()
                                ->default(0)
                                ->dehydrated(false),
                        ]),
                    ]),

                // ── STEP 3: TAXATION & THERMAL PRINTING ───────────────────────
                Forms\Components\Wizard\Step::make('Tax & Receipt')
                    ->description('GST rates, receipt format, and compliance')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Forms\Components\Toggle::make('enable_tax')
                            ->label('Enable Tax / GST')
                            ->default(false)
                            ->live()
                            ->dehydrated(false),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Select::make('tax_regime')
                                ->label('Tax Regime')
                                ->options([
                                    'cgst_sgst'   => 'Intra-State GST (CGST + SGST)',
                                    'igst'        => 'Inter-State GST (IGST)',
                                    'cgst_utgst'  => 'Union Territory GST (CGST + UTGST)',
                                    'custom'      => 'Custom Tax / VAT / Cess',
                                ])
                                ->default('cgst_sgst')
                                ->dehydrated(false),

                            Forms\Components\Select::make('tax_calculation_type')
                                ->label('Calculation Mode')
                                ->options([
                                    'exclusive' => 'Exclusive (Added on top of menu prices)',
                                    'inclusive' => 'Inclusive (Included inside menu prices)',
                                ])
                                ->default('exclusive')
                                ->dehydrated(false),

                            Forms\Components\Select::make('print_paper_size')
                                ->label('Thermal Receipt Format')
                                ->options([
                                    'thermal_58mm' => '58mm Thermal (Small outlets, food trucks)',
                                    'thermal_80mm' => '80mm Thermal (Standard restaurant receipt)',
                                    'a4'           => 'A4 Full Page Invoice',
                                ])
                                ->default('thermal_80mm')
                                ->dehydrated(false),
                        ]),

                        Forms\Components\TextInput::make('service_charge_percent')
                            ->label('Service Charge %')
                            ->helperText('Configurable service charge applied to dine-in orders.')
                            ->numeric()
                            ->default(0.00)
                            ->step(0.01)
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('fssai_lic_no')
                            ->label('FSSAI License Number')
                            ->placeholder('e.g. 10012345678901')
                            ->dehydrated(false),

                        Forms\Components\Textarea::make('print_header_text')
                            ->label('Receipt Header Note')
                            ->placeholder('e.g. Welcome to delicious dining!')
                            ->dehydrated(false),

                        Forms\Components\Textarea::make('print_footer_text')
                            ->label('Receipt Footer Note')
                            ->placeholder('e.g. Thank you! Visit us again.')
                            ->dehydrated(false),
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->badge()
                    ->color('warning')
                    ->placeholder('None')
                    ->searchable()
                    ->visible(fn() => auth()->user()?->is_super_admin),

                Tables\Columns\TextColumn::make('configuration.business_type')
                    ->label('Archetype')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'cafe'              => 'Cafe',
                        'quick_service'     => 'QSR / Food Truck',
                        'sweet_shop_outlet' => 'Sweet Shop / Retail',
                        'casual_dining'     => 'Casual Dining',
                        'fine_dining'       => 'Fine Dining',
                        'cloud_kitchen'     => 'Cloud Kitchen',
                        'hotel_resort'      => 'Hotel & Resort',
                        default             => ucfirst($state ?? 'Standard'),
                    }),

                Tables\Columns\TextColumn::make('phone_no')
                    ->label('Phone')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('configure')
                    ->label('Workflow & Config')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('primary')
                    ->url(fn(Restaurant $record) => $record->configuration 
                        ? RestaurantConfigurationResource::getUrl('edit', ['record' => $record->configuration->id])
                        : '#'
                    ),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRestaurants::route('/'),
            'create' => Pages\CreateRestaurant::route('/create'),
            'edit'   => Pages\EditRestaurant::route('/{record}/edit'),
        ];
    }
}