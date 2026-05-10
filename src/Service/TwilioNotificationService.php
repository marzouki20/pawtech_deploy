<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Twilio\Rest\Client;

class TwilioNotificationService
{
    private ?Client $twilio = null;
    private string $twilioPhoneNumber;
    private string $defaultCountryCode;
    private LoggerInterface $logger;
    private bool $isConfigured;

    public function __construct(
        ?string $accountSid,
        ?string $authToken,
        ?string $twilioPhoneNumber,
        ?string $defaultCountryCode,
        LoggerInterface $logger
    ) {
        $accountSid = trim((string) $accountSid);
        $authToken = trim((string) $authToken);
        $this->defaultCountryCode = trim((string) $defaultCountryCode) !== '' ? trim((string) $defaultCountryCode) : '+216';
        $this->twilioPhoneNumber = $this->normalizePhoneNumber(trim((string) $twilioPhoneNumber));
        $this->logger = $logger;

        $this->isConfigured = $accountSid !== '' && $authToken !== '' && $this->twilioPhoneNumber !== '';

        if ($this->isConfigured) {
            $this->twilio = new Client($accountSid, $authToken);
        } else {
            $this->logger->warning('Twilio is not configured. SMS notifications will be disabled.');
        }
    }

    public function sendConsultationNotification(
        string $veterinarianPhone,
        string $veterinarianName,
        string $dogName,
        string $consultationType,
        string $consultationDate
    ): bool {
        if (!$this->isConfigured || !$this->twilio) {
            $this->logger->info("SMS notification skipped (Twilio not configured): to {$veterinarianPhone}");
            return false;
        }

        $toPhone = $this->normalizePhoneNumber($veterinarianPhone);
        if ($toPhone === '') {
            $this->logger->warning("SMS notification skipped: invalid destination phone '{$veterinarianPhone}'");
            return false;
        }

        try {
            $message = sprintf(
                "New consultation created for %s.\nDog: %s\nType: %s\nDate: %s",
                $veterinarianName,
                $dogName,
                $consultationType,
                $consultationDate
            );

            $this->twilio->messages->create($toPhone, [
                'from' => $this->twilioPhoneNumber,
                'body' => $message,
            ]);

            $this->logger->info("SMS sent to veterinarian: {$toPhone}");
            return true;
        } catch (\Throwable $e) {
            $this->logger->error("Failed to send SMS to {$toPhone}: " . $e->getMessage());
            return false;
        }
    }

    private function normalizePhoneNumber(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }

        $phone = preg_replace('/\s+/', '', $phone) ?? '';
        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '+')) {
            return preg_match('/^\+\d{8,15}$/', $phone) ? $phone : '';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $asIntl = '+' . substr($digits, 2);
            return preg_match('/^\+\d{8,15}$/', $asIntl) ? $asIntl : '';
        }

        if (strlen($digits) === 8) {
            $asLocal = $this->defaultCountryCode . $digits;
            return preg_match('/^\+\d{8,15}$/', $asLocal) ? $asLocal : '';
        }

        $asIntl = '+' . $digits;
        return preg_match('/^\+\d{8,15}$/', $asIntl) ? $asIntl : '';
    }
}

