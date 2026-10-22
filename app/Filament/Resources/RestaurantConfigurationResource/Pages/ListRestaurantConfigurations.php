<?php

namespace App\Filament\Resources\RestaurantConfigurationResource\Pages;

use App\Filament\Resources\RestaurantConfigurationResource;
use Filament\Resources\Pages\ListRecords;

class ListRestaurantConfigurations extends ListRecords
{
    protected static string $resource = RestaurantConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
