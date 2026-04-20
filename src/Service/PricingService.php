<?php

namespace App\Service;

class PricingService
{
    public function calculateFinalPrice(int $basePrice): int
    {
        return (int) round($basePrice * 1.01);
    }

    public function calculateSystemFee(int $basePrice): int
    {
        return $this->calculateFinalPrice($basePrice) - $basePrice;
    }
}
