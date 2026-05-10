<?php

namespace App\Controller;

use App\Repository\CommandeRepository;
use App\Service\ClictopayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ClictopayController extends AbstractController
{
    #[Route('/clictopay/register', name: 'app_clictopay_register', methods: ['POST'])]
    public function registerPayment(
        Request $request,
        ClictopayService $clictopayService,
        CommandeRepository $commandeRepository,
        EntityManagerInterface $em
    ): Response {
        $orderId = $request->request->getInt('order_id');
        $amount = (float) $request->request->get('amount', 0);
        
        // Get order from database
        $commande = null;
        if ($orderId > 0) {
            $commande = $commandeRepository->find($orderId);
        }
        
        // Generate unique order number for Clictopay
        $orderNumber = 'ORD-' . uniqid() . '-' . time();
        
        // Convert amount to millimes (TND * 1000)
        $amountInMillimes = $clictopayService->convertToSmallestUnit($amount);
        
        // Build return URL with order ID
        $returnUrl = $this->generateUrl('app_clictopay_callback', ['orderId' => $orderId], \Symfony\Component\Routing\Router::ABSOLUTE_URL);
        
        try {
            $response = $clictopayService->registerPayment(
                $orderNumber,
                $amountInMillimes,
                ClictopayService::CURRENCY_TND,
                $returnUrl,
                'fr'
            );
            
            // Save Clictopay order ID to database if we have a local order
            if ($commande && isset($response['orderId'])) {
                // You might want to add a field to store the clictopay order ID
                // $commande->setClictopayOrderId($response['orderId']);
                $em->flush();
            }
            
            // Return the form URL for redirect
            if (isset($response['formUrl'])) {
                return $this->json([
                    'success' => true,
                    'orderId' => $response['orderId'] ?? null,
                    'formUrl' => $response['formUrl'],
                ]);
            }
            
            return $this->json([
                'success' => false,
                'error' => 'No form URL returned',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/clictopay/callback', name: 'app_clictopay_callback')]
    #[Route('/eshop/clictopay/callback', name: 'app_eshop_clictopay_callback')]
    public function callback(Request $request, CommandeRepository $commandeRepository, EntityManagerInterface $em, ClictopayService $clictopayService): Response
    {
        $orderId = (int) $request->query->get('orderId', 0);
        $orderNumber = $request->query->get('orderNumber', '');
        
        if ($orderId > 0) {
            $commande = $commandeRepository->find($orderId);
            if ($commande) {
                // Check payment status with Clictopay
                try {
                    $status = $clictopayService->getOrderStatus((string) $orderId);
                    
                    // OrderStatus: 1 = deposited (successful)
                    $success = isset($status['OrderStatus']) && $status['OrderStatus'] === ClictopayService::STATUS_DEPOSITED;
                    
                    $commande->setStatut($success);
                    $em->flush();
                    
                    if ($success) {
                        $this->addFlash('success', 'Paiement confirmé via ClictoPay.');
                    } else {
                        $this->addFlash('error', 'Paiement ClictoPay échoué ou annulé.');
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de la vérification du paiement: ' . $e->getMessage());
                }
            }
        }
        
        return $this->redirectToRoute('app_shop');
    }

    #[Route('/clictopay/status/{orderId}', name: 'app_clictopay_status')]
    public function getPaymentStatus(string $orderId, ClictopayService $clictopayService): Response
    {
        try {
            $status = $clictopayService->getOrderStatus($orderId);
            
            return $this->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/clictopay/status-extended/{orderId}', name: 'app_clictopay_status_extended')]
    public function getExtendedStatus(string $orderId, ClictopayService $clictopayService): Response
    {
        try {
            $status = $clictopayService->getOrderStatusExtended($orderId);
            
            return $this->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/clictopay/preauth', name: 'app_clictopay_preauth', methods: ['POST'])]
    public function registerPreAuth(Request $request, ClictopayService $clictopayService): Response
    {
        $amount = (float) $request->request->get('amount', 0);
        
        $orderNumber = 'PREAUTH-' . uniqid() . '-' . time();
        $amountInMillimes = $clictopayService->convertToSmallestUnit($amount);
        
        $returnUrl = $this->generateUrl('app_clictopay_callback', [], \Symfony\Component\Routing\Router::ABSOLUTE_URL);
        
        try {
            $response = $clictopayService->registerPreAuth(
                $orderNumber,
                $amountInMillimes,
                ClictopayService::CURRENCY_TND,
                $returnUrl,
                'fr'
            );
            
            if (isset($response['formUrl'])) {
                return $this->json([
                    'success' => true,
                    'orderId' => $response['orderId'] ?? null,
                    'formUrl' => $response['formUrl'],
                ]);
            }
            
            return $this->json([
                'success' => false,
                'error' => 'No form URL returned',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/clictopay/deposit/{orderId}', name: 'app_clictopay_deposit', methods: ['POST'])]
    public function depositPayment(string $orderId, Request $request, ClictopayService $clictopayService): Response
    {
        $amount = $request->request->get('amount');
        
        try {
            $result = $clictopayService->depositPayment($orderId, $amount ? (int) $amount : null);
            
            return $this->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/clictopay/reverse/{orderId}', name: 'app_clictopay_reverse', methods: ['POST'])]
    public function reversePayment(string $orderId, ClictopayService $clictopayService): Response
    {
        try {
            $result = $clictopayService->reversePayment($orderId);
            
            return $this->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/clictopay/refund/{orderId}', name: 'app_clictopay_refund', methods: ['POST'])]
    public function refundPayment(string $orderId, Request $request, ClictopayService $clictopayService, CommandeRepository $commandeRepository, EntityManagerInterface $em): Response
    {
        $amount = $request->request->get('amount');
        
        try {
            $result = $clictopayService->refundPayment($orderId, $amount ? (int) $amount : null);
            
            // Update local order status
            $orderNumber = $result['OrderNumber'] ?? null;
            if ($orderNumber) {
                $commande = $commandeRepository->findOneBy(['reference' => $orderNumber]);
                if ($commande) {
                    $commande->setStatut(false);
                    $em->flush();
                }
            }
            
            return $this->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/clictopay/check/{orderId}', name: 'app_clictopay_check')]
    public function checkPayment(string $orderId, ClictopayService $clictopayService): Response
    {
        try {
            $isSuccessful = $clictopayService->isPaymentSuccessful($orderId);
            
            return $this->json([
                'success' => true,
                'paid' => $isSuccessful,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
