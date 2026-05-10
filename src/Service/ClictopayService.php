<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ClictopayService
{
    // Currency codes (ISO 4217)
    public const CURRENCY_TND = 788; // Tunisian Dinar
    
    // Order status codes
    public const STATUS_CREATED = 0;
    public const STATUS_DEPOSITED = 1;
    public const STATUS_REVERSED = 2;
    public const STATUS_REFUNDED = 3;
    
    // Error codes
    public const ERROR_SUCCESS = 0;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ParameterBagInterface $params,
    ) {
    }

    /**
     * Get the appropriate base URL based on environment
     */
    private function getBaseUrl(): string
    {
        $isProduction = $this->params->has('clictopay.production')
            ? (bool) $this->params->get('clictopay.production')
            : false;
        
        if ($isProduction) {
            return 'https://ipay.clictopay.com/payment/rest';
        }
        
        // Default to sandbox URL
        return $this->params->get('clictopay.base_url') ?? 'https://test.clictopay.com/payment/rest';
    }

    /**
     * Get merchant username
     */
    private function getUsername(): string
    {
        $username = (string) $this->params->get('clictopay.username');
        
        if (empty($username)) {
            throw new \RuntimeException('ClictoPay username is not configured.');
        }
        
        return $username;
    }

    /**
     * Get merchant password
     */
    private function getPassword(): string
    {
        $password = (string) $this->params->get('clictopay.password');
        
        if (empty($password)) {
            throw new \RuntimeException('ClictoPay password is not configured.');
        }
        
        return $password;
    }

    /**
     * Initiate a payment transaction
     * 
     * @param string $orderNumber Unique order number in your system
     * @param int $amount Amount in smallest currency unit (e.g., cents for TND)
     * @param int $currency Currency code (ISO 4217). Default: 788 (TND)
     * @param string $returnUrl URL to redirect after successful payment
     * @param string|null $language Language code (ISO 639-1). Default: 'fr'
     * @return array Response with orderId and formUrl
     * @throws \RuntimeException On API error
     */
    public function registerPayment(
        string $orderNumber,
        int $amount,
        int $currency = self::CURRENCY_TND,
        ?string $returnUrl = null,
        ?string $language = 'fr'
    ): array {
        if ($returnUrl === null || $returnUrl === '') {
            throw new \InvalidArgumentException('Return URL is required.');
        }

        $baseUrl = $this->getBaseUrl();
        
        $payload = [
            'userName' => $this->getUsername(),
            'password' => $this->getPassword(),
            'orderNumber' => $orderNumber,
            'amount' => $amount,
            'currency' => $currency,
            'returnUrl' => $returnUrl,
        ];
        
        if ($language) {
            $payload['language'] = $language;
        }

        return $this->makeRequest('POST', $baseUrl . '/register.do', $payload);
    }

    /**
     * Get the status of a payment order
     * 
     * @param string $orderId The Clictopay order ID
     * @param string|null $language Language code
     * @return array Order status details
     * @throws \RuntimeException On API error
     */
    public function getOrderStatus(string $orderId, ?string $language = 'fr'): array
    {
        $baseUrl = $this->getBaseUrl();
        
        $query = [
            'orderId' => $orderId,
            'userName' => $this->getUsername(),
            'password' => $this->getPassword(),
        ];
        
        if ($language) {
            $query['language'] = $language;
        }

        return $this->makeRequest('GET', $baseUrl . '/getOrderStatus.do', [], $query);
    }

    /**
     * Check if payment was successful
     * 
     * @param string $orderId The Clictopay order ID
     * @return bool True if payment was successful
     */
    public function isPaymentSuccessful(string $orderId): bool
    {
        try {
            $status = $this->getOrderStatus($orderId);
            
            // OrderStatus: 1 = deposited (successful)
            return isset($status['OrderStatus']) && $status['OrderStatus'] === self::STATUS_DEPOSITED;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Initiate a pre-authorization transaction
     * (Amount is blocked but not captured)
     * 
     * @param string $orderNumber Unique order number
     * @param int $amount Amount in smallest currency unit
     * @param int $currency Currency code
     * @param string $returnUrl Return URL after payment
     * @param string|null $language Language code
     * @return array Response with orderId and formUrl
     */
    public function registerPreAuth(
        string $orderNumber,
        int $amount,
        int $currency = self::CURRENCY_TND,
        ?string $returnUrl = null,
        ?string $language = 'fr'
    ): array {
        if ($returnUrl === null || $returnUrl === '') {
            throw new \InvalidArgumentException('Return URL is required.');
        }

        $baseUrl = $this->getBaseUrl();
        
        $payload = [
            'userName' => $this->getUsername(),
            'password' => $this->getPassword(),
            'orderNumber' => $orderNumber,
            'amount' => $amount,
            'currency' => $currency,
            'returnUrl' => $returnUrl,
        ];
        
        if ($language) {
            $payload['language'] = $language;
        }

        return $this->makeRequest('POST', $baseUrl . '/registerPreAuth.do', $payload);
    }

    /**
     * Capture a pre-authorized payment
     * 
     * @param string $orderId The Clictopay order ID
     * @param int|null $amount Optional partial capture amount
     * @return array Capture result
     */
    public function depositPayment(string $orderId, ?int $amount = null): array
    {
        $baseUrl = $this->getBaseUrl();
        
        $payload = [
            'userName' => $this->getUsername(),
            'password' => $this->getPassword(),
            'orderId' => $orderId,
        ];
        
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        return $this->makeRequest('POST', $baseUrl . '/deposit.do', $payload);
    }

    /**
     * Reverse an authorized or unpaid order
     * 
     * @param string $orderId The Clictopay order ID
     * @return array Reverse result
     */
    public function reversePayment(string $orderId): array
    {
        $baseUrl = $this->getBaseUrl();
        
        $payload = [
            'userName' => $this->getUsername(),
            'password' => $this->getPassword(),
            'orderId' => $orderId,
        ];

        return $this->makeRequest('POST', $baseUrl . '/reverse.do', $payload);
    }

    /**
     * Refund a captured payment (full or partial)
     * 
     * @param string $orderId The Clictopay order ID
     * @param int|null $amount Optional partial refund amount
     * @return array Refund result
     */
    public function refundPayment(string $orderId, ?int $amount = null): array
    {
        $baseUrl = $this->getBaseUrl();
        
        $payload = [
            'userName' => $this->getUsername(),
            'password' => $this->getPassword(),
            'orderId' => $orderId,
        ];
        
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        return $this->makeRequest('POST', $baseUrl . '/refund.do', $payload);
    }

    /**
     * Get extended order status with card details
     * 
     * @param string $orderId The Clictopay order ID
     * @return array Extended order details
     */
    public function getOrderStatusExtended(string $orderId): array
    {
        $baseUrl = $this->getBaseUrl();
        
        $query = [
            'orderId' => $orderId,
            'userName' => $this->getUsername(),
            'password' => $this->getPassword(),
        ];

        return $this->makeRequest('GET', $baseUrl . '/getOrderStatusExtended.do', [], $query);
    }

    /**
     * Convert amount to smallest currency unit
     * For TND (788), amount should be in millimes (1 TND = 1000 millimes)
     * 
     * @param float $amount Amount in main currency unit
     * @param int $currency Currency code
     * @return int Amount in smallest unit
     */
    public function convertToSmallestUnit(float $amount, int $currency = self::CURRENCY_TND): int
    {
        switch ($currency) {
            case self::CURRENCY_TND:
                // TND uses millimes (1 TND = 1000 millimes)
                return (int) round($amount * 1000);
            default:
                // Most currencies use cents (1 unit = 100 cents)
                return (int) round($amount * 100);
        }
    }

    /**
     * Convert from smallest unit to main currency
     * 
     * @param int $amount Amount in smallest unit
     * @param int $currency Currency code
     * @return float Amount in main currency
     */
    public function convertFromSmallestUnit(int $amount, int $currency = self::CURRENCY_TND): float
    {
        switch ($currency) {
            case self::CURRENCY_TND:
                return $amount / 1000;
            default:
                return $amount / 100;
        }
    }

    /**
     * Make an HTTP request to the Clictopay API
     * 
     * @param string $method HTTP method
     * @param string $endpoint Full URL
     * @param array $payload Request body data
     * @param array $query Query parameters
     * @return array API response
     * @throws \RuntimeException On API error
     */
    private function makeRequest(
        string $method,
        string $endpoint,
        array $payload = [],
        array $query = []
    ): array {
        $options = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
        ];

        if (!empty($payload)) {
            $options['body'] = http_build_query($payload);
        }

        if (!empty($query)) {
            $options['query'] = $query;
        }

        try {
            $response = $this->client->request($method, $endpoint, $options);
            $data = $response->toArray(false);
        } catch (ClientExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface | TransportExceptionInterface $e) {
            throw new \RuntimeException('ClictoPay request failed: ' . $e->getMessage(), 0, $e);
        }

        // Check for error in response
        if (isset($data['ErrorCode']) && $data['ErrorCode'] !== '0' && $data['ErrorCode'] !== 0) {
            throw new \RuntimeException(
                $data['ErrorMessage'] ?? 'ClictoPay returned an error: ' . ($data['ErrorCode'] ?? 'Unknown')
            );
        }

        return $data;
    }

    /**
     * Parse callback URL parameters
     * 
     * @param Request $request Symfony request object
     * @return array Parsed callback parameters
     */
    public function parseCallback(Request $request): array
    {
        return [
            'orderId' => $request->query->get('orderId'),
            'orderNumber' => $request->query->get('orderNumber'),
            'status' => $request->query->get('status'),
        ];
    }
}
