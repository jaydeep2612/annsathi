<?php

namespace App\Filament\Resources\RestaurantConfigurationResource\Pages;

use App\Filament\Resources\RestaurantConfigurationResource;
use App\Services\WorkflowEngineService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditRestaurantConfiguration extends EditRecord
{
    protected static string $resource = RestaurantConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reevaluate_orders')
                ->label('Self-Heal Active Orders')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Re-evaluate & Self-Heal Active Orders')
                ->modalDescription('If staff roles have changed (e.g. all waiters on leave), this will automatically reroute stalled orders to counter fulfillment or kitchen bypass without losing data.')
                ->action(function (WorkflowEngineService $service) {
                    $record = $this->getRecord();
                    $healed = $service->reevaluateActiveOrders($record->restaurant);

                    Notification::make()
                        ->title('Self-Healing Completed')
                        ->body("Successfully updated {$healed} active orders according to new workflow rules.")
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function afterSave(): void
    {
        // Whenever workflow preset or roles are changed, automatically self-heal active orders
        $record = $this->getRecord();
        if ($record->restaurant) {
            $healed = app(WorkflowEngineService::class)->reevaluateActiveOrders($record->restaurant);
            if ($healed > 0) {
                Notification::make()
                    ->title('Orders Automatically Adapted')
                    ->body("Adjusted {$healed} active order(s) to match the new workflow preset.")
                    ->info()
                    ->send();
            }
        }
    }
}
