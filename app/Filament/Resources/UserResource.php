<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Staff & Attendance';
    protected static ?string $navigationLabel = 'Staff Members';
    protected static ?string $modelLabel = 'Staff Member';
    protected static ?string $pluralModelLabel = 'Staff Members';
    protected static ?int $navigationSort = -1;

    /* ---------------------------------------------------
     | ACCESS CONTROL
     |---------------------------------------------------*/
    public static function canAccess(): bool
    {
        return auth()->check() && (
            auth()->user()->isSuperAdmin()
            || auth()->user()->isOwner()
            || auth()->user()->isRestaurantAdmin()
            || auth()->user()->isManager()
        );
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $currentUser = auth()->user();
        if (!$currentUser) return false;

        if ($currentUser->isSuperAdmin() || $currentUser->isOwner()) {
            return true;
        }

        // Self edit
        if ($currentUser->id === $record->id) {
            return true;
        }

        $targetRole = strtolower(str_replace([' ', '-'], '_', $record->role?->name ?? ''));

        if ($currentUser->isRestaurantAdmin()) {
            return in_array($targetRole, ['manager', 'captain', 'chef', 'waiter', 'cashier']);
        }

        if ($currentUser->isManager()) {
            return in_array($targetRole, ['captain', 'chef', 'waiter', 'cashier']);
        }

        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $currentUser = auth()->user();
        if (!$currentUser || $currentUser->id === $record->id) {
            return false; // Cannot delete self
        }

        if ($currentUser->isSuperAdmin() || $currentUser->isOwner()) {
            return true;
        }

        $targetRole = strtolower(str_replace([' ', '-'], '_', $record->role?->name ?? ''));

        if ($currentUser->isRestaurantAdmin()) {
            return in_array($targetRole, ['manager', 'captain', 'chef', 'waiter', 'cashier']);
        }

        if ($currentUser->isManager()) {
            return in_array($targetRole, ['captain', 'chef', 'waiter', 'cashier']);
        }

        return false;
    }

    /* ---------------------------------------------------
     | DATA ISOLATION
     |---------------------------------------------------*/
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        if (!$user) return $query->where('id', -1);

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isOwner()) {
            $ownedIds = $user->ownedRestaurants()->pluck('id');
            return $query->whereIn('restaurant_id', $ownedIds);
        }

        if ($user->isRestaurantAdmin() || $user->isManager()) {
            return $query->where('restaurant_id', $user->restaurant_id);
        }

        return $query->where('id', -1);
    }

    /* ---------------------------------------------------
     | FORM
     |---------------------------------------------------*/
    public static function form(Form $form): Form
    {
        $user = auth()->user();
        $restaurantSelection = [];

        if ($user->isSuperAdmin()) {
            $restaurantSelection[] = Forms\Components\Select::make('restaurant_id')
                ->label('Restaurant')
                ->options(Restaurant::pluck('name', 'id'))
                ->searchable()
                ->nullable();

        } elseif ($user->isOwner()) {
            $restaurantSelection[] = Forms\Components\Select::make('restaurant_id')
                ->label('Restaurant')
                ->options($user->ownedRestaurants()->pluck('name', 'id'))
                ->searchable()
                ->required();

        } else {
            $restaurantSelection[] = Forms\Components\Hidden::make('restaurant_id')
                ->default($user->restaurant_id);
        }

        $mainFields = [
            Forms\Components\Section::make('Personal & Account Credentials')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(fn($operation) => $operation === 'create')
                    ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn($state) => filled($state)),

                Forms\Components\Select::make('role_id')
                    ->label('Primary Role')
                    ->required()
                    ->reactive()
                    ->options(fn() => self::availableRoles()),

                Forms\Components\TextInput::make('monthly_salary')
                    ->label('Monthly Salary')
                    ->numeric()
                    ->prefix('₹')
                    ->placeholder('e.g. 15000')
                    ->minValue(0)
                    ->visible(fn(Forms\Get $get) => in_array(
                        Role::find($get('role_id'))?->name,
                        ['manager', 'captain', 'chef', 'waiter', 'cashier']
                    )),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active Staff Member')
                    ->default(true),
            ])->columns(2),
        ];

        return $form->schema(array_merge($restaurantSelection, $mainFields));
    }

    /* ---------------------------------------------------
     | TABLE
     |---------------------------------------------------*/
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->badge()
                    ->color('info')
                    ->visible(fn() => auth()->user()?->isSuperAdmin() || auth()->user()?->isOwner()),

                Tables\Columns\TextColumn::make('role.label')
                    ->label('Role')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'Super Admin'       => 'danger',
                        'Owner'             => 'warning',
                        'Restaurant Admin'  => 'info',
                        'Manager'           => 'primary',
                        'Chef'              => 'success',
                        'Waiter'            => 'warning',
                        'Cashier'           => 'gray',
                        default             => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Active')
                    ->since(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    /* ---------------------------------------------------
     | ROLE FILTER
     |---------------------------------------------------*/
    protected static function availableRoles(): array
    {
        $user = auth()->user();
        $allRoles = Role::all();
        if (!$user) return [];

        if ($user->isSuperAdmin()) {
            return $allRoles->pluck('label', 'id')->toArray();
        }

        if ($user->isOwner()) {
            return $allRoles->filter(fn($r) => !in_array($r->name, ['super_admin']))
                ->pluck('label', 'id')
                ->toArray();
        }

        if ($user->isRestaurantAdmin()) {
            $allowed = ['manager', 'captain', 'chef', 'waiter', 'cashier'];
            return $allRoles->filter(fn($r) => in_array($r->name, $allowed))
                ->pluck('label', 'id')
                ->toArray();
        }

        if ($user->isManager()) {
            $allowed = ['captain', 'chef', 'waiter', 'cashier'];
            return $allRoles->filter(fn($r) => in_array($r->name, $allowed))
                ->pluck('label', 'id')
                ->toArray();
        }

        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
