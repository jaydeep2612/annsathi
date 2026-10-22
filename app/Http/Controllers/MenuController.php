<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function view(
        Restaurant $restaurant,
        RestaurantTable $table,
        string $token
    ) {
        // 🔐 Security check
        abort_unless($table->restaurant_id === $restaurant->id, 404);
        abort_unless($table->qr_token === $token, 403);
        abort_unless($table->is_active, 403);

        return view('menu.view', [
            'restaurant' => $restaurant,
            'table' => $table,
        ]);
    }
}
