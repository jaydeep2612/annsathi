<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KitchenQueueResource\Pages;
use App\Models\KitchenQueue;
use App\Models\OrderStatusLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use App\Events\OrderStatusUpdated;
class KitchenQueueResource extends Resource
{
    protected static ?string $model = KitchenQueue::class;
    protected static bool $shouldRegisterNavigation = true;
    protected static ?string $navigationIcon = 'heroicon-o-fire';
    protected static ?string $navigationLabel = 'Kitchen Display';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 3;

    /*
    |--------------------------------------------------------------------------
    | ACCESS CONTROL (Chef, Manager, Admin)
    |--------------------------------------------------------------------------
    */

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->is_super_admin) return true;
        return in_array($user->role?->name ?? '', ['chef', 'manager', 'restaurant_admin']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY (Only Current Restaurant Orders)
    |--------------------------------------------------------------------------
    */

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'order.table',
                'order.items'
            ])
            ->whereHas('order', function ($query) {
                $query->where('restaurant_id', auth()->user()->restaurant_id);
            })
            ->orderByRaw("FIELD(current_status, 'placed', 'preparing', 'ready')")
            ->orderBy('created_at', 'asc');
    }


    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    /*
    |--------------------------------------------------------------------------
    | TABLE (Kitchen Display Board)
    |--------------------------------------------------------------------------
    */

    public static function table(Table $table): Table
    {
        return $table
            // ->poll('5s')
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->columns([
                Tables\Columns\Layout\Stack::make([

                    /*
                    |--------------------------------------------------------------------------
                    | HEADER (Table No + Timer)
                    |--------------------------------------------------------------------------
                    */
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('order.table.table_number')
                            ->prefix('Table: ')
                            ->weight(FontWeight::Black)
                            ->size(Tables\Columns\TextColumn\TextColumnSize::Large)
                            ->color('primary'),

                        Tables\Columns\TextColumn::make('created_at')
                            ->since()
                            ->icon('heroicon-m-clock')
                            ->color('gray')
                            ->alignEnd(),
                    ]),

                    /*
                    |--------------------------------------------------------------------------
                    | CUSTOMER
                    |--------------------------------------------------------------------------
                    */
                    Tables\Columns\TextColumn::make('order.customer_name')
                        ->icon('heroicon-m-user')
                        ->color('gray'),

                    /*
                    |--------------------------------------------------------------------------
                    | ITEMS (Item-by-Item Breakdown & Progress Tracking)
                    |--------------------------------------------------------------------------
                    */
                    Tables\Columns\TextColumn::make('order.items')
                        ->formatStateUsing(function ($record) {

                            if (!$record->order || !$record->order->items) {
                                return ' - ';
                            }

                            return new HtmlString(
                                $record->order->items->map(function ($item) {

                                    $notes = $item->notes
                                        ? "<div class='text-xs text-red-600 font-bold italic ml-6 mt-1'>
                                            ⚠️ {$item->notes}
                                       </div>"
                                        : "";

                                    $isReadymade = ($item->item_type === 'readymade');
                                    
                                    $statusBadge = match ($item->status) {
                                        'cooking' => "<span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800 animate-pulse'>🔥 Cooking</span>",
                                        'ready'   => "<span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800'>✅ Ready</span>",
                                        'served'  => "<span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-700'>🍽️ Served</span>",
                                        default   => $isReadymade 
                                            ? "<span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800'>📦 Readymade (Ready)</span>"
                                            : "<span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800'>⏳ Pending</span>",
                                    };

                                    $typeIcon = $isReadymade ? '📦' : '🍳';

                                    return "
                                    <div class='mb-3 border-b border-gray-200 pb-2'>
                                        <div class='flex items-center justify-between'>
                                            <div class='flex items-center'>
                                                <span class='font-black text-lg mr-2'>
                                                    {$item->quantity} x 
                                                </span>
                                                <span class='font-medium text-gray-800'>
                                                    {$typeIcon} {$item->item_name}
                                                </span>
                                            </div>
                                            <div>
                                                {$statusBadge}
                                            </div>
                                        </div>
                                        {$notes}
                                    </div>
                                ";
                                })->implode('')
                            );
                        })
                        ->html(),

                    /*
                    |--------------------------------------------------------------------------
                    | STATUS (BIGGER + UPPERCASE)
                    |--------------------------------------------------------------------------
                    */
                    Tables\Columns\TextColumn::make('current_status')
                        ->badge()
                        ->size(Tables\Columns\TextColumn\TextColumnSize::Large)
                        ->color(fn(string $state): string => match ($state) {
                            'placed' => 'danger',
                            'preparing' => 'warning',
                            'ready' => 'success',
                            default => 'gray',
                        })
                        ->extraAttributes(['class' => 'text-lg font-bold mt-4'])
                        ->alignCenter(),

                ])->space(3),
            ])

            /*
            |--------------------------------------------------------------------------
            | ACTION BUTTONS
            |--------------------------------------------------------------------------
            */
            ->actions([

                // MANAGE INDIVIDUAL ITEM PROGRESS
                Tables\Actions\Action::make('update_item_status')
                    ->label('Item Statuses')
                    ->icon('heroicon-m-adjustments-horizontal')
                    ->button()
                    ->color('info')
                    ->modalHeading(fn($record) => 'Order #' . ($record->order->id ?? '') . ' Item Progress')
                    ->form(function ($record) {
                        $items = $record->order->items ?? [];
                        $fields = [];
                        foreach ($items as $item) {
                            $isReadymade = ($item->item_type === 'readymade');
                            $typeLabel = $isReadymade ? '📦 Readymade' : '🍳 Prepared';

                            $fields[] = \Filament\Forms\Components\Select::make("item_status_{$item->id}")
                                ->label("{$item->quantity}x {$item->item_name} ({$typeLabel})")
                                ->options([
                                    'pending' => '⏳ Pending Prep',
                                    'cooking' => '🔥 Cooking',
                                    'ready'   => '✅ Ready',
                                    'served'  => '🍽️ Served',
                                ])
                                ->default($item->status ?? ($isReadymade ? 'ready' : 'pending'))
                                ->required();
                        }
                        return $fields;
                    })
                    ->action(function ($record, array $data) {
                        $workflowEngine = app(\App\Services\WorkflowEngineService::class);
                        $order = $record->order;

                        foreach ($order->items as $item) {
                            $fieldKey = "item_status_{$item->id}";
                            if (isset($data[$fieldKey])) {
                                $newStatus = $data[$fieldKey];
                                if ($newStatus !== $item->status) {
                                    if ($newStatus === 'cooking') {
                                        $workflowEngine->handleStartCookingItem($item);
                                    } elseif ($newStatus === 'ready') {
                                        $workflowEngine->handleItemReady($item);
                                    } elseif ($newStatus === 'served') {
                                        $workflowEngine->handleItemServed($item);
                                    } else {
                                        $item->update(['status' => $newStatus]);
                                    }
                                }
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Item Progress Updated')
                            ->success()
                            ->send();
                    }),

                // START COOKING (WHOLE ORDER)
                Tables\Actions\Action::make('start_cooking')
                    ->label('Start Cooking')
                    ->icon('heroicon-m-play')
                    ->button()
                    ->color('warning')
                    ->visible(
                        fn($record) =>
                        in_array($record->current_status, ['placed', 'in_kitchen', 'pending'])
                    )
                    ->action(function ($record) {
                        $record->update(['current_status' => 'preparing']);
                        app(\App\Services\WorkflowEngineService::class)->handleStartCooking($record->order);
                    }),

                // MARK READY (WHOLE ORDER)
                Tables\Actions\Action::make('mark_ready')
                    ->label('Mark Ready')
                    ->icon('heroicon-m-check-badge')
                    ->button()
                    ->color('success')
                    ->visible(
                        fn($record) =>
                        $record->current_status === 'preparing'
                    )
                    ->action(function ($record) {
                        $record->update(['current_status' => 'ready', 'completed_at' => now()]);
                        app(\App\Services\WorkflowEngineService::class)->handleFoodReady($record->order);
                        \App\Events\OrderStatusUpdated::dispatch($record->order->fresh());
                    }),

            ])

            ->bulkActions([]);
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS UPDATE LOGIC
    |--------------------------------------------------------------------------
    */

    public static function updateStatus($record, $newStatus): void
    {
        $oldStatus = $record->current_status;

        // Update Kitchen Queue
        $record->update([
            'current_status' => $newStatus,
        ]);

        // Update Main Order
        $record->order->update([
            'status' => $newStatus,
        ]);

        // Create Status Log
        OrderStatusLog::create([
            'order_id' => $record->order_id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'changed_by' => auth()->id(),
        ]);
        OrderStatusUpdated::dispatch($record->order);
    }

    /*
    |--------------------------------------------------------------------------
    | PAGES
    |--------------------------------------------------------------------------
    */

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKitchenQueues::route('/'),
        ];
    }
}
