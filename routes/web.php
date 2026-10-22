<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('admin');
});

// Multi-Restaurant Context Switcher for Owner & Super Admin
Route::get('/owner/switch-restaurant/{id}', function ($id) {
    $user = auth()->user();
    if ($user && ($user->isOwner() || $user->is_super_admin)) {
        if ($user->is_super_admin || $user->ownedRestaurants()->where('id', $id)->exists()) {
            session(['active_restaurant_id' => (int) $id]);
            $user->update(['restaurant_id' => (int) $id]);
        }
    }
    return redirect()->back();
})->middleware(['web', 'auth'])->name('owner.switch-restaurant');
