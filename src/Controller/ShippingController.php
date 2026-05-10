<?php

namespace App\Controller;

use App\Service\ShippingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ShippingController extends AbstractController
{
    private ShippingService $shippingService;

    public function __construct(ShippingService $shippingService)
    {
        $this->shippingService = $shippingService;
    }

    #[Route('/api/shipping/calculate', name: 'app_shipping_calculate', methods: ['POST'])]
    public function calculateShipping(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $orderTotal = floatval($data['order_total'] ?? 0);
        $totalWeight = floatval($data['weight'] ?? 0);
        $zone = $data['zone'] ?? 'tunis';
        
        $result = $this->shippingService->calculateShipping($orderTotal, $totalWeight, $zone);
        
        // Add free shipping progress info
        $result['free_shipping_progress'] = [
            'qualifies' => $this->shippingService->qualifiesForFreeShipping($orderTotal),
            'amount_needed' => $this->shippingService->getAmountForFreeShipping($orderTotal),
            'threshold' => 200,
        ];
        
        return $this->json($result);
    }

    #[Route('/api/shipping/zones', name: 'app_shipping_zones', methods: ['GET'])]
    public function getZones(): JsonResponse
    {
        return $this->json($this->shippingService->getZones());
    }

    #[Route('/api/shipping/express', name: 'app_shipping_express', methods: ['POST'])]
    public function calculateExpressShipping(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $orderTotal = floatval($data['order_total'] ?? 0);
        $totalWeight = floatval($data['weight'] ?? 0);
        
        $result = $this->shippingService->calculateExpressShipping($orderTotal, $totalWeight);
        
        return $this->json($result);
    }

    #[Route('/api/shipping/track/{trackingNumber}', name: 'app_shipping_track', methods: ['GET'])]
    public function trackPackage(string $trackingNumber): JsonResponse
    {
        $result = $this->shippingService->getTrackingStatus($trackingNumber);
        
        return $this->json($result);
    }
}
