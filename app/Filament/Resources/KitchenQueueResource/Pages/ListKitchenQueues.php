<?php

namespace App\Filament\Resources\KitchenQueueResource\Pages;

use App\Filament\Resources\KitchenQueueResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;

class ListKitchenQueues extends ListRecords
{
    public static string $resource = KitchenQueueResource::class;
    public function getListeners(): array
    {
        $restaurantId = auth()->user()->restaurant_id;

        return [
            "echo-private:restaurant.{$restaurantId},.OrderStatusUpdated" => '$refresh',
        ];
    }
    public function getTabs(): array
    {
        return [
            'placed' => Tab::make()
                ->modifyQueryUsing(fn ($query) =>
                    $query->where('current_status', 'placed')
                ),

            'preparing' => Tab::make()
                ->modifyQueryUsing(fn ($query) =>
                    $query->where('current_status', 'preparing')
                ),

            'ready' => Tab::make()
                ->modifyQueryUsing(fn ($query) =>
                    $query->where('current_status', 'ready')
                ),
        ];
    }
}
