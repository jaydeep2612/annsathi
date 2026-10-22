<?php

namespace App\Filament\Traits;

trait AuthorizesUserResource
{
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->is_super_admin || $user->isOwner()) return true;

        return $user->hasResourcePermission(static::getResourceKey(), 'viewAny');
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->is_super_admin || $user->isOwner()) return true;

        return $user->hasResourcePermission(static::getResourceKey(), 'create');
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->is_super_admin || $user->isOwner()) return true;

        return $user->hasResourcePermission(static::getResourceKey(), 'update');
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->is_super_admin || $user->isOwner()) return true;

        return $user->hasResourcePermission(static::getResourceKey(), 'delete');
    }

    abstract protected static function getResourceKey(): string;
}
