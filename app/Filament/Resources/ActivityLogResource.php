<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Activity & Audit Logs';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 99;

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

        // Tenant scope
        if ($user && !$user->is_super_admin) {
            if ($user->isOwner()) {
                $ownedIds = $user->ownedRestaurants()->pluck('id');
                $query->whereIn('restaurant_id', $ownedIds);
            } elseif ($user->restaurant_id) {
                $query->where('restaurant_id', $user->restaurant_id);
            }
        }

        return $query->latest('id');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Audit Event Details')->schema([
                Forms\Components\TextInput::make('created_at')->label('Timestamp')->disabled(),
                Forms\Components\TextInput::make('user_name')->label('User')->disabled(),
                Forms\Components\TextInput::make('user_role')->label('Role')->disabled(),
                Forms\Components\TextInput::make('action')->label('Action')->disabled(),
                Forms\Components\TextInput::make('model_type')->label('Model')->disabled(),
                Forms\Components\TextInput::make('model_id')->label('Record ID')->disabled(),
                Forms\Components\TextInput::make('ip_address')->label('IP Address')->disabled(),
                Forms\Components\Textarea::make('user_agent')->label('User Agent')->disabled()->columnSpanFull(),
                Forms\Components\Textarea::make('description')->label('Description')->disabled()->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('State Changes (Diff)')->schema([
                Forms\Components\KeyValue::make('old_values')->label('Previous Values (Before)')->disabled(),
                Forms\Components\KeyValue::make('new_values')->label('Updated Values (After)')->disabled(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('d M Y, h:i:s A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->visible(fn() => auth()->user()?->is_super_admin || auth()->user()?->isOwner()),

                Tables\Columns\TextColumn::make('user_name')
                    ->label('Performed By')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user_role')
                    ->label('Role')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'owner'       => 'warning',
                        'manager'     => 'info',
                        'chef'        => 'success',
                        'waiter'      => 'primary',
                        default       => 'gray',
                    }),

                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Operation')
                    ->limit(40),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
                Tables\Filters\SelectFilter::make('user_role')
                    ->options([
                        'super_admin'      => 'Super Admin',
                        'owner'            => 'Owner',
                        'restaurant_admin' => 'Admin',
                        'manager'          => 'Manager',
                        'waiter'           => 'Waiter',
                        'chef'             => 'Chef',
                        'cashier'          => 'Cashier',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view'  => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
