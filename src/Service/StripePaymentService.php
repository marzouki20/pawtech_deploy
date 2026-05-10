<?php

namespace App\Service;

use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class StripePaymentService
{
    private StripeClient $stripe;
    
    public function __construct(
        private readonly ParameterBagInterface $params,
    ) {
        $secretKey = $this->getSecretKey();
        $this->stripe = new StripeClient($secretKey);
    }

    /**
     * Get Stripe publishable key
     */
    public function getPublishableKey(): string
    {
        if ($this->params->has('stripe.publishable_key')) {
            return (string) $this->params->get('stripe.publishable_key');
        }

        return (string) ($_ENV['STRIPE_KEY'] ?? $_SERVER['STRIPE_KEY'] ?? '');
    }

    /**
     * Get Stripe secret key
     */
    private function getSecretKey(): string
    {
        $key = '';
        if ($this->params->has('stripe.secret_key')) {
            $key = (string) $this->params->get('stripe.secret_key');
        } else {
            $key = (string) ($_ENV['STRIPE_SECRET'] ?? $_SERVER['STRIPE_SECRET'] ?? '');
        }
        
        if (empty($key)) {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }
        
        return $key;
    }

    /**
     * Create a checkout session for Stripe Checkout
     */
    public function createCheckoutSession(
        int $amount,
        string $currency,
        string $orderId,
        string $customerEmail,
        ?string $successUrl = null,
        ?string $cancelUrl = null
    ): \Stripe\Checkout\Session {
        $sessionParams = [
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'Order #' . $orderId,
                        ],
                        'unit_amount' => $amount, // Amount in smallest currency unit (cents)
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'success_url' => $successUrl ?? 'https://yourdomain.com/payment/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl ?? 'https://yourdomain.com/payment/cancel',
            'metadata' => [
                'order_id' => $orderId,
            ],
        ];
        
        if ($customerEmail) {
            $sessionParams['customer_email'] = $customerEmail;
        }
        
        return $this->stripe->checkout->sessions->create($sessionParams);
    }

    /**
     * Create a payment intent (for custom payment flow)
     */
    public function createPaymentIntent(
        int $amount,
        string $currency,
        ?array $metadata = []
    ): \Stripe\PaymentIntent {
        return $this->stripe->paymentIntents->create([
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => $metadata,
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);
    }

    /**
     * Retrieve a checkout session
     */
    public function getCheckoutSession(string $sessionId): \Stripe\Checkout\Session
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId);
    }

    /**
     * Retrieve a payment intent
     */
    public function getPaymentIntent(string $paymentIntentId): \Stripe\PaymentIntent
    {
        return $this->stripe->paymentIntents->retrieve($paymentIntentId);
    }

    /**
     * Check if payment was successful
     */
    public function isPaymentSuccessful(string $paymentIntentId): bool
    {
        try {
            $intent = $this->getPaymentIntent($paymentIntentId);
            return $intent->status === 'succeeded';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Convert amount to smallest currency unit
     */
    public function convertToSmallestUnit(float $amount, string $currency = 'tnd'): int
    {
        // Most currencies use cents (100)
        // TND (Tunisian Dinar) uses millimes (1000)
        // Some currencies like JPY use no decimal places
        $noDecimalCurrencies = ['jpy', 'krw', 'vnd', 'idf'];
        
        if (in_array(strtolower($currency), $noDecimalCurrencies)) {
            return (int) round($amount);
        }
        
        // TND uses millimes (1 TND = 1000 millimes)
        if (strtolower($currency) === 'tnd') {
            return (int) round($amount * 1000);
        }
        
        return (int) round($amount * 100);
    }

    /**
     * Refund a payment
     */
    public function refundPayment(string $paymentIntentId, ?int $amount = null): \Stripe\Refund
    {
        $params = ['payment_intent' => $paymentIntentId];
        
        if ($amount !== null) {
            $params['amount'] = $amount;
        }
        
        return $this->stripe->refunds->create($params);
    }
}
