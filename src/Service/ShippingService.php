<?php

namespace App\Service;

class ShippingService
{
    // Shipping rates (can be moved to parameters.yml or database)
    private const BASE_RATE = 8; // Base shipping cost in TND
    private const FREE_SHIPPING_THRESHOLD = 200; // Free shipping for orders above 200 TND
    
    // Weight-based rates (TND per kg)
    private const WEIGHT_RATE = 2;
    
    // Delivery zones
    private const ZONES = [
        'tunis' => ['name' => 'Tunis & Suburbs', 'multiplier' => 1.0, 'days' => '1-2'],
        'north' => ['name' => 'North Tunisia', 'multiplier' => 1.5, 'days' => '2-3'],
        'south' => ['name' => 'South Tunisia', 'multiplier' => 2.0, 'days' => '3-5'],
        'international' => ['name' => 'International', 'multiplier' => 3.5, 'days' => '7-14'],
    ];

    /**
     * Calculate shipping cost based on order total and weight
     */
    public function calculateShipping(float $orderTotal, float $totalWeight = 0, string $zone = 'tunis'): array
    {
        // Free shipping for orders above threshold
        if ($orderTotal >= self::FREE_SHIPPING_THRESHOLD) {
            return [
                'cost' => 0,
                'free' => true,
                'zone' => self::ZONES[$zone] ?? self::ZONES['tunis'],
                'delivery_days' => self::ZONES[$zone]['days'] ?? '1-2',
            ];
        }

        // Calculate base cost
        $weightCost = $totalWeight * self::WEIGHT_RATE;
        $baseCost = self::BASE_RATE + $weightCost;
        
        // Apply zone multiplier
        $zoneData = self::ZONES[$zone] ?? self::ZONES['tunis'];
        $shippingCost = $baseCost * $zoneData['multiplier'];

        return [
            'cost' => round($shippingCost, 2),
            'free' => false,
            'zone' => $zoneData,
            'delivery_days' => $zoneData['days'],
            'breakdown' => [
                'base' => self::BASE_RATE,
                'weight_charge' => round($weightCost, 2),
                'zone_multiplier' => $zoneData['multiplier'],
            ],
        ];
    }

    /**
     * Get available shipping zones
     */
    public function getZones(): array
    {
        return self::ZONES;
    }

    /**
     * Get shipping cost for a specific zone
     */
    public function getZoneCost(string $zone): array
    {
        return self::ZONES[$zone] ?? self::ZONES['tunis'];
    }

    /**
     * Check if order qualifies for free shipping
     */
    public function qualifiesForFreeShipping(float $orderTotal): bool
    {
        return $orderTotal >= self::FREE_SHIPPING_THRESHOLD;
    }

    /**
     * Get amount needed for free shipping
     */
    public function getAmountForFreeShipping(float $currentTotal): float
    {
        if ($currentTotal >= self::FREE_SHIPPING_THRESHOLD) {
            return 0;
        }
        return self::FREE_SHIPPING_THRESHOLD - $currentTotal;
    }

    /**
     * Generate tracking number for an order
     */
    public function generateTrackingNumber(): string
    {
        return 'PWT-' . strtoupper(uniqid()) . '-' . rand(100, 999);
    }

    /**
     * Simulate tracking status (can be integrated with real shipping APIs)
     */
    public function getTrackingStatus(string $trackingNumber): array
    {
        // This would typically integrate with a real shipping API
        // For now, we return a simulated status
        $statuses = [
            'processing' => ['label' => 'Processing', 'description' => 'Order is being prepared'],
            'shipped' => ['label' => 'Shipped', 'description' => 'Package has been dispatched'],
            'in_transit' => ['label' => 'In Transit', 'description' => 'Package is on its way'],
            'out_for_delivery' => ['label' => 'Out for Delivery', 'description' => 'Package is out for delivery'],
            'delivered' => ['label' => 'Delivered', 'description' => 'Package has been delivered'],
        ];

        return [
            'tracking_number' => $trackingNumber,
            'status' => $statuses['processing'],
            'estimated_delivery' => date('Y-m-d', strtotime('+3 days')),
            'history' => [
                ['status' => $statuses['processing'], 'timestamp' => date('Y-m-d H:i:s')],
            ],
        ];
    }

    /**
     * Calculate shipping with express option
     */
    public function calculateExpressShipping(float $orderTotal, float $totalWeight = 0): array
    {
        $standard = $this->calculateShipping($orderTotal, $totalWeight);
        
        // Express is 2x the standard rate
        $expressCost = $standard['cost'] * 2;
        
        return [
            'standard' => $standard,
            'express' => [
                'cost' => round($expressCost, 2),
                'delivery_days' => 'Same day / Next day',
                'multiplier' => 2,
            ],
        ];
    }
}
