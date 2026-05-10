<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Donation;
use App\Entity\LigneCommande;
use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;
use App\Service\StripePaymentService;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class StripeController extends AbstractController
{
    #[Route('/stripe/checkout', name: 'app_stripe_checkout', methods: ['GET', 'POST'])]
    public function checkout(
        Request $request,
        StripePaymentService $stripeService,
        SessionInterface $session
    ): Response {
        // Get parameters from query or request
        $orderId = $request->query->get('order_id') ?? $request->request->get('order_id');
        $amount = $request->query->get('amount') ?? $request->request->get('amount');
        $currency = $request->query->get('currency', 'eur');
        
        // Convert to cents (EUR uses cents: 1 EUR = 100)
        if ($amount && is_numeric($amount)) {
            $amountInCents = (int) round($amount * 100);
        } else {
            $amountInCents = 500; // Default 5 EUR for testing
            $amount = 5.00;
        }
        
        // Get Stripe publishable key
        $stripeKey = $_ENV['STRIPE_KEY'] ?? $stripeService->getPublishableKey();
        
        // Store cart data in session for order creation after payment
        $cartData = $request->request->get('cart_data');
        if ($cartData) {
            $session->set('stripe_cart_data', json_decode($cartData, true));
            $session->set('stripe_total', $amountInCents);
        }
        
        return $this->render('stripe/index.html.twig', [
            'stripe_key' => $stripeKey,
            'order_id' => $orderId ?? 'order-' . time(),
            'amount' => $amountInCents,
            'currency' => $currency,
        ]);
    }

    #[Route('/stripe/create-charge', name: 'app_stripe_charge', methods: ['POST'])]
    public function createCharge(
        Request $request,
        CommandeRepository $commandeRepository,
        ProduitRepository $produitRepository,
        EntityManagerInterface $em,
        SessionInterface $session,
        RecommendationService $recommendationService
    ): Response {
        $stripeKey = $_ENV['STRIPE_SECRET'] ?? '';
        
        if (empty($stripeKey)) {
            $this->addFlash('error', 'Stripe secret key is not configured.');
            return $this->redirectToRoute('app_stripe_checkout');
        }
        
        $stripe = new StripeClient($stripeKey);
        
        $token = $request->request->get('stripeToken');
        $orderId = $request->request->get('order_id');
        $amount = $request->request->get('amount', 500);
        $currency = $request->request->get('currency', 'eur');
        
        if (empty($token)) {
            $this->addFlash('error', 'Invalid payment token.');
            return $this->redirectToRoute('app_stripe_checkout');
        }
        
        try {
            // Create the charge
            $charge = $stripe->charges->create([
                'amount' => (int) $amount,
                'currency' => $currency,
                'source' => $token,
                'description' => 'Order Payment',
                'metadata' => [
                    'order_id' => $orderId ?? '',
                ],
            ]);
            
            // Get cart data from session
            $cartData = $session->get('stripe_cart_data', []);
            
            // Create order AFTER successful payment
            if (!empty($cartData)) {
                $commande = new Commande();
                $commande->setDate(new \DateTime());
                $commande->setStatut(true); // Payment successful
                $commande->setTotal($amount / 100); // Convert from cents to EUR
                
                $em->persist($commande);
                
                // Create order lines from cart
                $orderLines = [];
                foreach ($cartData as $item) {
                    $produit = $produitRepository->find($item['id']);
                    if ($produit) {
                        $ligneCommande = new LigneCommande();
                        $ligneCommande->setProduit($produit);
                        $ligneCommande->setQuantite($item['quantity']);
                        $ligneCommande->setPrixUnitaire((float) $produit->getPrix());
                        $ligneCommande->setCommande($commande);
                        
                        // Update product stock
                        $currentStock = $produit->getQuantite();
                        $produit->setQuantite($currentStock - $item['quantity']);
                        
                        $em->persist($ligneCommande);
                        $orderLines[] = $ligneCommande;
                    }
                }
                
                $em->flush();
                
                // Update product ratings based on purchase
                $recommendationService->updateRatingsForOrder($orderLines);
                
                // Clear session cart and Stripe data
                $session->remove('stripe_cart_data');
                $session->remove('stripe_total');
                $session->set('cart', []);
                
                $this->addFlash('success', 'Payment successful! Order #' . $commande->getId() . ' created.');
            } else {
                $this->addFlash('success', 'Payment successful! Thank you for your order.');
            }
            
            return $this->redirectToRoute('app_shop');
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Payment failed: ' . $e->getMessage());
            return $this->redirectToRoute('app_stripe_checkout', [
                'order_id' => $orderId,
                'amount' => $amount / 100,
                'currency' => $currency,
            ]);
        }
    }

    #[Route('/stripe/payment-intent', name: 'app_stripe_payment_intent', methods: ['POST'])]
    public function createPaymentIntent(
        Request $request,
        StripePaymentService $stripeService
    ): Response {
        $amount = (float) $request->request->get('amount');
        $currency = $request->request->get('currency', 'tnd');
        $orderId = $request->request->get('order_id');
        
        try {
            $amountInMillimes = $stripeService->convertToSmallestUnit($amount, $currency);
            
            $intent = $stripeService->createPaymentIntent(
                $amountInMillimes,
                $currency,
                ['order_id' => $orderId]
            );
            
            return $this->json([
                'success' => true,
                'clientSecret' => $intent->client_secret,
                'publishableKey' => $stripeService->getPublishableKey(),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/stripe/success', name: 'app_stripe_success', methods: ['GET'])]
    public function success(): Response
    {
        $this->addFlash('success', 'Payment successful! Thank you for your order.');
        return $this->redirectToRoute('app_shop');
    }

    #[Route('/stripe/cancel', name: 'app_stripe_cancel', methods: ['GET'])]
    public function cancel(SessionInterface $session): Response
    {
        // Clear Stripe session data
        $session->remove('stripe_cart_data');
        $session->remove('stripe_total');
        
        $this->addFlash('error', 'Payment was cancelled.');
        return $this->redirectToRoute('app_shop');
    }

    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        $payload = $request->getContent();
        
        // Handle webhook events
        $event = json_decode($payload, true);
        
        switch ($event['type']) {
            case 'charge.succeeded':
                // Payment succeeded
                break;
            case 'charge.failed':
                // Payment failed
                break;
        }
        
        return new Response('OK', Response::HTTP_OK);
    }

    #[Route('/stripe/verify/{paymentIntentId}', name: 'app_stripe_verify')]
    public function verifyPayment(string $paymentIntentId, StripePaymentService $stripeService): Response
    {
        try {
            $isSuccessful = $stripeService->isPaymentSuccessful($paymentIntentId);
            
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

    // =====================
    // DONATION ROUTES
    // =====================

    #[Route('/stripe/donation', name: 'app_stripe_donation_checkout', methods: ['GET', 'POST'])]
    public function donationCheckout(
        Request $request,
        StripePaymentService $stripeService,
        SessionInterface $session
    ): Response {
        $amount = $request->query->get('amount') ?? $request->request->get('amount');
        $donateur = $request->query->get('donateur') ?? $request->request->get('donateur');
        $email = $request->query->get('email') ?? $request->request->get('email');
        $currency = 'eur';
        
        // Convert to millimes
        if ($amount && is_numeric($amount)) {
            $amountInMillimes = (int) round($amount * 1000);
        } else {
            $amountInMillimes = 10000; // Default 10 TND
            $amount = 10.00;
        }
        
        // Store donation info in session
        $session->set('stripe_donation', [
            'donateur' => $donateur,
            'email' => $email,
            'amount' => $amount,
        ]);
        
        $stripeKey = $_ENV['STRIPE_KEY'] ?? $stripeService->getPublishableKey();
        
        return $this->render('stripe/donation.html.twig', [
            'stripe_key' => $stripeKey,
            'amount' => $amountInMillimes,
            'currency' => $currency,
            'donateur' => $donateur,
            'email' => $email,
        ]);
    }

    #[Route('/stripe/donation/charge', name: 'app_stripe_donation_charge', methods: ['POST'])]
    public function donationCharge(
        Request $request,
        EntityManagerInterface $em,
        SessionInterface $session
    ): Response {
        $stripeKey = $_ENV['STRIPE_SECRET'] ?? '';
        
        if (empty($stripeKey)) {
            $this->addFlash('error', 'Stripe secret key is not configured.');
            return $this->redirectToRoute('app_donation');
        }
        
        $stripe = new StripeClient($stripeKey);
        
        $token = $request->request->get('stripeToken');
        $amount = $request->request->get('amount', 10000);
        $currency = 'eur';
        
        if (empty($token)) {
            $this->addFlash('error', 'Invalid payment token.');
            return $this->redirectToRoute('app_donation');
        }
        
        try {
            $charge = $stripe->charges->create([
                'amount' => (int) $amount,
                'currency' => $currency,
                'source' => $token,
                'description' => 'Donation',
            ]);
            
            // Get donation info from session
            $donationInfo = $session->get('stripe_donation', []);
            
            // Create donation record
            $donation = new Donation();
            $donation->setMontant($amount / 100); // Convert from cents to EUR
            $donation->setDate(new \DateTime());
            $donation->setDonateur($donationInfo['donateur'] ?? 'Anonymous');
            $donation->setEmail($donationInfo['email'] ?? '');
            $donation->setStatut(true); // Payment successful
            $donation->setReference('DON-' . time());
            
            $em->persist($donation);
            $em->flush();
            
            // Clear session
            $session->remove('stripe_donation');
            
            $this->addFlash('donation_success', 'Thank you for your donation of ' . ($amount / 1000) . ' TND!');
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Donation failed: ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('app_donation');
    }
}
