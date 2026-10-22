<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isRestaurantAdmin()
            || $user->isManager();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin()
            || $user->restaurant_id === $model->restaurant_id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isRestaurantAdmin()
            || $user->isManager();
    }

    public function update(User $user, User $model): bool
    {
        if ($model->isSuperAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->restaurant_id === $model->restaurant_id;
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->id === $user->id) {
            return false; // prevent self-delete
        }

        return $user->isSuperAdmin();
    }
}
