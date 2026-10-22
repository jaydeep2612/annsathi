<?php

namespace App\Filament\Resources\TableBillingResource\Pages;

use App\Filament\Resources\TableBillingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTableBilling extends EditRecord
{
    protected static string $resource = TableBillingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
