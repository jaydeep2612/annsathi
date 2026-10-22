<?php

namespace App\Filament\Resources\KitchenQueueResource\Pages;

use App\Filament\Resources\KitchenQueueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKitchenQueue extends EditRecord
{
    protected static string $resource = KitchenQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
