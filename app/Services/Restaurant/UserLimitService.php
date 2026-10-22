<?php

namespace App\Services\Restaurant;

use App\Models\Restaurant;
use Illuminate\Validation\ValidationException;

class UserLimitService
{
    public function enforce(Restaurant $restaurant): void
    {
        if (! $restaurant->user_limits) {
            return;
        }

        $currentUsers = $restaurant->users()
            ->where('is_active', true)
            ->count();

        if ($currentUsers >= $restaurant->user_limits) {
            throw ValidationException::withMessages([
                'restaurant_id' => 'User limit exceeded for this restaurant.',
            ]);
        }
    }
}
