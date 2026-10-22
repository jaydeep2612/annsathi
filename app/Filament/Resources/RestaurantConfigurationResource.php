<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RestaurantConfigurationResource\Pages;
use App\Models\RestaurantConfiguration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RestaurantConfigurationResource extends Resource
{
    protected static ?string $model = RestaurantConfiguration::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Restaurant Configurations';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->is_super_admin || $user->isOwner() || $user->isRestaurantAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && !$user->is_super_admin) {
            if ($user->isOwner()) {
                $ownedIds = $user->ownedRestaurants()->pluck('id');
                $query->whereIn('restaurant_id', $ownedIds);
            } elseif ($user->restaurant_id) {
                $query->where('restaurant_id', $user->restaurant_id);
            }
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Configuration Tabs')->tabs([

                // ── TAB 1: BUSINESS ARCHETYPE & BASIC WORKFLOW ────────────────────────
                Forms\Components\Tabs\Tab::make('Business & Workflow')->schema([
                    Forms\Components\Select::make('restaurant_id')
                        ->relationship('restaurant', 'name')
                        ->required()
                        ->disabled(fn(string $operation) => $operation === 'edit')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('business_type')
                        ->label('Business Archetype')
                        ->options([
                            'cafe'               => 'Cafe & Coffee Shop (Single Operator / Quick Pickup)',
                            'quick_service'      => 'Fast Food / QSR / Food Truck (Token / High Throughput)',
                            'sweet_shop_outlet'  => 'Sweet Shop / Retail Counter (Direct Billing)',
                            'casual_dining'      => 'Casual Dining (Table QR, Waiter & Chef)',
                            'fine_dining'        => 'Fine Dining / Multi-Floor (Captain, Waiter, Multi-Station KOT)',
                            'cloud_kitchen'      => 'Cloud Kitchen (Kitchen Display & Parcel only)',
                            'hotel_resort'       => 'Hotel & Resort (Room QR Dining + Dine-in)',
                        ])
                        ->required()
                        ->live(),

                    Forms\Components\Select::make('workflow_preset')
                        ->label('Workflow Preset')
                        ->options([
                            'solo_manager'        => 'Solo Manager (Single person handles order, prep & billing)',
                            'manager_waiter'      => 'Manager + Waiter (Waiters take/serve; Manager oversees)',
                            'manager_waiter_chef' => 'Standard Dining (Chef in kitchen + Waiter + Manager)',
                            'full_crew'           => 'Full Crew (Captain + Waiter + Chef + Cashier + Manager)',
                            'custom'              => 'Custom Dynamic Workflow (Individually configure below)',
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
                        }),

                    Forms\Components\CheckboxList::make('active_workflow_roles')
                        ->label('Active Staff Roles in Workflow')
                        ->options([
                            'manager' => 'Manager',
                            'captain' => 'Captain',
                            'waiter'  => 'Waiter',
                            'chef'    => 'Chef / Cook',
                            'cashier' => 'Cashier',
                        ])
                        ->helperText('Uncheck roles when staff are on leave (e.g. unchecking Waiters auto-routes ready food to Counter).')
                        ->columns(3)
                        ->columnSpanFull(),

                    Forms\Components\Section::make('Dispatch & Routing Flags')->schema([
                        Forms\Components\Toggle::make('require_order_approval')
                            ->label('Require Order Approval')
                            ->helperText('Customer QR orders pause in Pending Approval until staff/manager confirms.')
                            ->default(true),

                        Forms\Components\Toggle::make('waiter_can_take_orders')
                            ->label('Allow Waiters to Place Orders')
                            ->helperText('When disabled, waiters are strictly restricted to serving and table clearing.')
                            ->default(true),

                        Forms\Components\Toggle::make('kitchen_order_dispatch')
                            ->label('Kitchen Order Dispatch (KOT)')
                            ->helperText('Dispatches order items to the Kitchen/Chef queue.')
                            ->default(true),

                        Forms\Components\Toggle::make('waiter_serving_dispatch')
                            ->label('Waiter Serving Dispatch')
                            ->helperText('Dispatches notification to waiters when food is ready.')
                            ->default(true),

                        Forms\Components\Toggle::make('counter_billing_dispatch')
                            ->label('Counter Billing Dispatch')
                            ->helperText('Routes settled orders through cashier/counter.')
                            ->default(true),

                        Forms\Components\Toggle::make('auto_complete_on_payment')
                            ->label('Auto-Complete on Payment')
                            ->helperText('Closes order automatically once paid.')
                            ->default(true),
                    ])->columns(2),

                    Forms\Components\Section::make('Service Channels & Tokens')->schema([
                        Forms\Components\CheckboxList::make('enabled_service_modes')
                            ->label('Enabled Ordering Channels')
                            ->options([
                                'dine_in'         => 'Table Dine-In (Table QR)',
                                'takeaway_parcel' => 'Takeaway / Parcel (Parcel QR)',
                                'room_service'    => 'Room Service (Room QR)',
                                'counter_pos'     => 'Direct Counter POS',
                            ])
                            ->columns(2),

                        Forms\Components\Toggle::make('token_system_enabled')
                            ->label('Enable Daily Token System')
                            ->helperText('Generates daily tokens (#01, #02...) for counter/QSR pickups.')
                            ->default(false),
                    ])->columns(2),
                ]),

                // ── TAB 2: MULTI-TYPE TAX & BILLING ENGINE ────────────────────────────
                Forms\Components\Tabs\Tab::make('Taxation & Billing')->schema([
                    Forms\Components\Toggle::make('enable_tax')
                        ->label('Enable Tax / GST')
                        ->live()
                        ->default(false),

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
                            ->live(),

                        Forms\Components\Select::make('tax_calculation_type')
                            ->label('Calculation Mode')
                            ->options([
                                'exclusive' => 'Exclusive (Added on top of food prices)',
                                'inclusive' => 'Inclusive (Included inside menu prices)',
                                'exempt'    => 'Tax Exempt',
                            ])
                            ->default('exclusive'),

                        Forms\Components\Select::make('tax_level')
                            ->label('Tax Level')
                            ->options([
                                'order_total' => 'Order Total (Apply flat % components across order)',
                                'item_level'  => 'Item-by-Item (Calculate per-item using item tax slab)',
                            ])
                            ->default('order_total'),
                    ])->visible(fn(Forms\Get $get) => (bool) $get('enable_tax')),

                    Forms\Components\Repeater::make('tax_components')
                        ->label('Tax Components Breakdown')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Component Name')
                                ->placeholder('e.g. CGST, SGST, Cess')
                                ->required(),

                            Forms\Components\Select::make('type')
                                ->label('Tax Type')
                                ->options([
                                    'CGST'  => 'CGST (Central GST)',
                                    'SGST'  => 'SGST (State GST)',
                                    'IGST'  => 'IGST (Integrated GST)',
                                    'UTGST' => 'UTGST (Union Territory GST)',
                                    'CESS'  => 'CESS',
                                    'OTHER' => 'VAT / Custom Tax',
                                ])
                                ->required(),

                            Forms\Components\TextInput::make('rate')
                                ->label('Rate %')
                                ->numeric()
                                ->step(0.01)
                                ->required(),

                            Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ])
                        ->columns(4)
                        ->default([
                            ['name' => 'CGST', 'type' => 'CGST', 'rate' => 2.5, 'is_active' => true],
                            ['name' => 'SGST', 'type' => 'SGST', 'rate' => 2.5, 'is_active' => true],
                        ])
                        ->visible(fn(Forms\Get $get) => (bool) $get('enable_tax'))
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('service_charge_percent')
                        ->label('Dine-In Service Charge %')
                        ->numeric()
                        ->default(0.00)
                        ->step(0.01),

                    Forms\Components\Repeater::make('additional_charges')
                        ->label('Additional Configured Charges (e.g., Packaging Fee, Delivery)')
                        ->schema([
                            Forms\Components\TextInput::make('name')->required()->placeholder('e.g. Packaging Fee'),
                            Forms\Components\Select::make('type')->options(['fixed' => 'Fixed Amount', 'percent' => 'Percentage %'])->default('fixed')->required(),
                            Forms\Components\TextInput::make('value')->numeric()->step(0.01)->required(),
                            Forms\Components\Toggle::make('is_active')->default(true),
                        ])
                        ->columns(4)
                        ->columnSpanFull(),
                ]),

                // ── TAB 3: THERMAL BILL PRINTING & COMPLIANCE ─────────────────────────
                Forms\Components\Tabs\Tab::make('Thermal Printing')->schema([
                    Forms\Components\Select::make('print_paper_size')
                        ->label('Paper Format')
                        ->options([
                            'thermal_58mm' => '58mm Thermal (Small outlets, food trucks, mobile)',
                            'thermal_80mm' => '80mm Thermal (Standard restaurant receipt)',
                            'a4'           => 'A4 Full Page Invoice',
                        ])
                        ->default('thermal_80mm'),

                    Forms\Components\TextInput::make('fssai_lic_no')
                        ->label('FSSAI License Number')
                        ->placeholder('e.g. 10012345678901'),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Toggle::make('print_show_logo')
                            ->label('Show Restaurant Logo')
                            ->default(true),

                        Forms\Components\Toggle::make('print_show_upi_qr')
                            ->label('Print Dynamic UPI QR')
                            ->default(true),

                        Forms\Components\Toggle::make('print_show_tax_breakup')
                            ->label('Show Itemized Tax Lines')
                            ->default(true),
                    ]),

                    Forms\Components\Textarea::make('print_header_text')
                        ->label('Custom Receipt Header Note')
                        ->placeholder('e.g. Welcome to Delicious Cafe!'),

                    Forms\Components\Textarea::make('print_footer_text')
                        ->label('Custom Receipt Footer Note')
                        ->placeholder('e.g. Thank you for visiting! Please come again.'),
                ]),

                // ── TAB 4: MODULAR SAAS MODULES & LIMITS ──────────────────────────────
                Forms\Components\Tabs\Tab::make('Modules & Limits')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('table_capacity_limit')
                            ->label('Table Capacity Limit')
                            ->numeric()
                            ->default(20),

                        Forms\Components\TextInput::make('user_capacity_limit')
                            ->label('Staff User Limit')
                            ->numeric()
                            ->default(10),

                        Forms\Components\TextInput::make('rooms_capacity_limit')
                            ->label('Rooms Limit')
                            ->numeric()
                            ->default(0),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Toggle::make('is_pay_first')
                            ->label('Pay-First Ordering (QSR / Cafe Model)')
                            ->helperText('Customers must pay before orders are dispatched to prep.')
                            ->default(false),

                        Forms\Components\Toggle::make('is_all_in_one_dashboard')
                            ->label('Enable All-in-One Dashboard (Single Screen POS)')
                            ->helperText('Manager screen handles order placement, kitchen & serving.')
                            ->default(false),

                        Forms\Components\Toggle::make('is_rooms_facility')
                            ->label('Enable Hotel Rooms Facility')
                            ->helperText('Enables hotel room QR ordering and room session folios.')
                            ->default(false),

                        Forms\Components\Toggle::make('has_inventory')
                            ->label('Enable Inventory & Stock Control')
                            ->helperText('Enables stock tracking on menu items and stock deduction.')
                            ->default(false),

                        Forms\Components\Toggle::make('has_detailed_inventory')
                            ->label('Enable Recipe-Based Auto-Inventory')
                            ->helperText('Automated raw ingredient deduction on kitchen order acceptance.')
                            ->default(false),

                        Forms\Components\Toggle::make('has_attendance')
                            ->label('Enable Attendance & Staff Payroll')
                            ->helperText('Manages staff daily check-ins, shifts, and salary tracking.')
                            ->default(false),
                    ]),
                ]),

                // ── TAB 5: FUTURE EXTENSIBLE CUSTOM FEATURES ──────────────────────────
                Forms\Components\Tabs\Tab::make('Future Custom Features')->schema([
                    Forms\Components\KeyValue::make('custom_features')
                        ->label('Custom Feature Flags & Settings (Key-Value)')
                        ->helperText('Add arbitrary future flags, loyalty settings, third-party delivery webhooks, or kiosk parameters on the fly without schema migrations.')
                        ->keyLabel('Feature Key (e.g. loyalty_points_enabled)')
                        ->valueLabel('Value (e.g. true, 1.5, standard)')
                        ->columnSpanFull(),
                ]),

            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('business_type')
                    ->label('Archetype')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'cafe'              => 'Cafe',
                        'quick_service'     => 'QSR / Food Truck',
                        'sweet_shop_outlet' => 'Sweet Shop / Retail',
                        'casual_dining'     => 'Casual Dining',
                        'fine_dining'       => 'Fine Dining',
                        'cloud_kitchen'     => 'Cloud Kitchen',
                        'hotel_resort'      => 'Hotel & Resort',
                        default             => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('workflow_preset')
                    ->label('Workflow')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\IconColumn::make('enable_tax')
                    ->label('Tax')
                    ->boolean(),

                Tables\Columns\TextColumn::make('print_paper_size')
                    ->label('Printer')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('table_capacity_limit')
                    ->label('Table Limit')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_pay_first')
                    ->label('Pay First')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestaurantConfigurations::route('/'),
            'edit'  => Pages\EditRestaurantConfiguration::route('/{record}/edit'),
        ];
    }
}
