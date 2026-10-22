<?php

namespace App\Actions\Restaurant;

use App\Services\Restaurant\RestaurantOnboardingService;

class CreateRestaurantAction
{
    public function __construct(
        private RestaurantOnboardingService $service
    ) {}

    public function execute(array $data)
    {
        return $this->service->onboard($data);
    }
}
