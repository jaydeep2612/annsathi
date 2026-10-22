<?php

namespace App\Services;

use App\Models\Order;

class BillingCalculationService
{
    /**
     * Compute full financial breakdown for an order according to restaurant's tax & billing configuration.
     *
     * @param Order $order
     * @return array{
     *   subtotal: float,
     *   tax_breakup: array,
     *   total_tax: float,
     *   service_charge: float,
     *   discount_amount: float,
     *   grand_total: float
     * }
     */
    public function calculate(Order $order, float $discountAmount = 0.00): array
    {
        $config = $order->restaurant?->configuration;
        $items = $order->items ?? $order->orderItems;
        if (($items === null || $items->isEmpty()) && $order->exists) {
            $items = $order->items()->get();
        }

        $subtotal = 0.00;
        foreach ($items as $item) {
            $price = (float) ($item->price > 0 ? $item->price : ($item->unit_price ?? 0));
            $qty = (int) ($item->quantity ?? 1);
            $subtotal += ($price * $qty);
        }

        $taxBreakup = [];
        $totalTax = 0.00;

        if ($config && $config->enable_tax && !empty($config->tax_components)) {
            $isInclusive = ($config->tax_calculation_type === 'inclusive');

            if ($config->tax_level === 'order_total') {
                $activeComponents = collect($config->tax_components)
                    ->filter(fn($c) => ($c['is_active'] ?? true) === true);

                $combinedRate = (float) $activeComponents->sum('rate');

                foreach ($activeComponents as $comp) {
                    $rate = (float) ($comp['rate'] ?? 0);
                    $name = $comp['name'] ?? $comp['type'] ?? 'GST';

                    if ($isInclusive && $combinedRate > 0) {
                        // Reverse calculate included tax: (Amount * Rate) / (100 + CombinedRate)
                        $taxAmount = round(($subtotal * $rate) / (100 + $combinedRate), 2);
                    } else {
                        // Exclusive tax: (Amount * Rate) / 100
                        $taxAmount = round(($subtotal * $rate) / 100, 2);
                    }

                    $taxBreakup[] = [
                        'name'   => $name,
                        'type'   => $comp['type'] ?? 'GST',
                        'rate'   => $rate,
                        'amount' => $taxAmount,
                    ];
                    $totalTax += $taxAmount;
                }
            } else {
                // Item-level tax calculation
                foreach ($items as $item) {
                    $itemPrice = (float) ($item->price > 0 ? $item->price : ($item->unit_price ?? 0));
                    $itemQty = (int) ($item->quantity ?? 1);
                    $itemTotal = $itemPrice * $itemQty;
                    $itemRate = (float) ($item->tax_rate ?? $item->menuItem?->tax_rate ?? 5.0);

                    if ($isInclusive) {
                        $itemTax = round(($itemTotal * $itemRate) / (100 + $itemRate), 2);
                    } else {
                        $itemTax = round(($itemTotal * $itemRate) / 100, 2);
                    }

                    $totalTax += $itemTax;
                }

                $taxBreakup[] = [
                    'name'   => 'Itemized Tax',
                    'type'   => 'ITEM_TAX',
                    'rate'   => null,
                    'amount' => round($totalTax, 2),
                ];
            }
        }

        // Service charge
        $serviceCharge = 0.00;
        if ($config && $config->service_charge_percent > 0) {
            $serviceCharge = round(($subtotal * (float) $config->service_charge_percent) / 100, 2);
        }

        // Grand Total Calculation
        // In inclusive tax: Grand Total = Subtotal + Service Charge - Discount (tax already in prices)
        // In exclusive tax: Grand Total = Subtotal + Total Tax + Service Charge - Discount
        if ($config && $config->tax_calculation_type === 'inclusive') {
            $grandTotal = max(0, ($subtotal + $serviceCharge) - $discountAmount);
        } else {
            $grandTotal = max(0, ($subtotal + $totalTax + $serviceCharge) - $discountAmount);
        }

        return [
            'subtotal'        => round($subtotal, 2),
            'tax_breakup'     => $taxBreakup,
            'total_tax'       => round($totalTax, 2),
            'service_charge'  => round($serviceCharge, 2),
            'discount_amount' => round($discountAmount, 2),
            'grand_total'     => round($grandTotal, 2),
        ];
    }
}
