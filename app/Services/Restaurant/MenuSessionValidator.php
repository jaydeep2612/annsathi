<?php

namespace App\Services\Restaurant;

use App\Models\QrSession;
use App\Models\RestaurantTable;

class MenuSessionValidator
{
    public function validate(RestaurantTable $table, string $sessionToken): QrSession
    {
        $session = QrSession::where('session_token', $sessionToken)
            ->where('restaurant_table_id', $table->id)
            ->where('is_active', true)
            ->first();

        if (! $session) {
            abort(403, 'Invalid or inactive session');
        }

        if ($session->isExpired()) {
            $session->update(['is_active' => false]);
            abort(403, 'Session expired');
        }

        return $session;
    }
}
