<?php

namespace App\Service;

use App\Entity\Participation;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;

/**
 * Service for sending event-related emails
 * Includes QR code generation for participation verification
 */
class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailerFrom
    ) {}

    /**
     * Generate QR code for participation verification
     * Returns raw PNG data for email embedding
     */
    private function generateQrCode(Participation $participation): ?string
    {
        try {
            if (
                !class_exists(QrCode::class)
                || !class_exists(PngWriter::class)
                || !class_exists(Encoding::class)
            ) {
                $this->logger->warning('QR code library not installed. Sending email without QR code.');
                return null;
            }
            if (!extension_loaded('gd')) {
                $this->logger->warning('GD extension is not enabled. Sending email without QR code.');
                return null;
            }

            $user = $participation->getUser();
            $event = $participation->getEvenement();
            
            // QR code content - clean ticket format
            $participantName = $user->getPrenom() . ' ' . $user->getNom();
            $eventDate = $event->getDateDebut()?->format('d/m/Y H:i') ?? 'TBD';
            $location = $event->getLieu() . ', ' . $event->getVille();
            $qrData = "PAWTECH EVENT TICKET\n"
                . "Ticket: #" . $participation->getId() . "\n"
                . "Event: " . $event->getTitre() . "\n"
                . "Date: " . $eventDate . "\n"
                . "Location: " . $location . "\n"
                . "Type: " . $event->getType() . "\n"
                . "Participant: " . $participantName . "\n"
                . "Email: " . $user->getEmail() . "\n"
                . "Status: CONFIRMED";
            // Create QR code (endroid/qr-code v5.x API)
            $qrCode = new QrCode(
                data: $qrData,
                encoding: new Encoding('UTF-8'),
                size: 250,
                margin: 10
            );
            
            // Write to PNG and return raw data
            $writer = new PngWriter();
            $result = $writer->write($qrCode);

            return $result->getString();
            
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate QR code: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send confirmation email when admin approves a participation
     * Now includes QR code for event check-in
     */
    public function sendParticipationConfirmation(Participation $participation): bool
    {
        $user = $participation->getUser();
        $event = $participation->getEvenement();

        if (!$user || !$event) {
            $this->logger->error('Cannot send confirmation email: missing user or event');
            return false;
        }

        $userEmail = $user->getEmail();
        if (!$userEmail) {
            $this->logger->error('Cannot send confirmation email: user has no email');
            return false;
        }

        // Generate QR code as raw PNG data
        $qrCodeData = $this->generateQrCode($participation);
        
        $htmlContent = $this->buildConfirmationEmailHtml($participation, $qrCodeData !== null);
        $textContent = $this->buildConfirmationEmailText($participation);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($userEmail)
            ->subject('Your participation is confirmed - ' . $event->getTitre())
            ->text($textContent)
            ->html($htmlContent);

        // Attach QR code as embedded image if generated successfully
        if ($qrCodeData !== null) {
            $email->embed($qrCodeData, 'qrcode', 'image/png');
        }

        try {
            $this->mailer->send($email);
            $this->logger->info(
                $qrCodeData !== null
                    ? 'Confirmation email with QR code sent to ' . $userEmail
                    : 'Confirmation email sent without QR code to ' . $userEmail
            );
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send confirmation email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Build HTML content for confirmation email with QR code
     */
    private function buildConfirmationEmailHtml(Participation $participation, bool $hasQrCode): string
    {
        $user = $participation->getUser();
        $event = $participation->getEvenement();

        $userName = $user->getPrenom() ?? 'Participant';
        $eventTitle = $event->getTitre();
        $eventDate = $event->getDateDebut()?->format('l, F j, Y \a\t H:i') ?? 'TBD';
        $eventEndDate = $event->getDateFin()?->format('l, F j, Y \a\t H:i') ?? '';
        $eventLocation = $event->getLieu();
        $eventCity = $event->getVille();
        $eventType = $event->getType();
        $eventDescription = $event->getDescription() ?? '';
        $participationId = $participation->getId();

        // QR code section - uses cid: reference for embedded image
        $qrCodeSection = '';
        $entryInstruction = 'Present your confirmation email at the entrance for check-in.';
        if ($hasQrCode) {
            $qrCodeSection = <<<HTML
            <!-- QR Code Section -->
            <div style="background:#f0fdf4;border-radius:12px;padding:25px;margin-bottom:25px;text-align:center;border:2px dashed #22c55e;">
                <h3 style="color:#166534;margin:0 0 15px;font-size:18px;">Your Event Ticket</h3>
                <img src="cid:qrcode" alt="QR Code Ticket" style="width:200px;height:200px;"/>
                <p style="color:#166534;font-size:12px;margin:15px 0 0;">
                    <strong>Ticket #{$participationId}</strong><br>
                    Show this QR code at the event entrance
                </p>
            </div>
HTML;
            $entryInstruction = 'Present your QR code at the entrance for quick check-in.';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;font-family:Arial,sans-serif;background-color:#f5f5f5;">
    <div style="max-width:600px;margin:0 auto;background-color:#ffffff;">
        <!-- Header -->
        <div style="background:linear-gradient(135deg,#f97316 0%,#fb923c 100%);padding:30px;text-align:center;">
            <h1 style="color:#ffffff;margin:0;font-size:28px;">You're In!</h1>
            <p style="color:#fff3e0;margin:10px 0 0;font-size:16px;">Your participation has been confirmed</p>
        </div>

        <!-- Content -->
        <div style="padding:30px;">
            <p style="font-size:16px;color:#333;margin:0 0 20px;">
                Hello <strong>{$userName}</strong>,
            </p>
            <p style="font-size:16px;color:#333;margin:0 0 25px;">
                Great news! Your registration for the following event has been approved:
            </p>

            <!-- Event Card -->
            <div style="background:#fff7ed;border-radius:12px;padding:25px;border-left:4px solid #f97316;margin-bottom:25px;">
                <h2 style="color:#c2410c;margin:0 0 15px;font-size:22px;">{$eventTitle}</h2>
                
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="padding:8px 0;color:#333;">
                            <strong>Date:</strong> {$eventDate}
                            {$this->formatEndDate($eventEndDate)}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#333;">
                            <strong>Location:</strong> {$eventLocation}, {$eventCity}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#333;">
                            <strong>Type:</strong> {$eventType}
                        </td>
                    </tr>
                </table>

                {$this->formatDescription($eventDescription)}
            </div>

            {$qrCodeSection}

            <!-- Important Info -->
            <div style="background:#fef3c7;border-radius:8px;padding:15px;margin-bottom:25px;">
                <p style="margin:0;color:#92400e;font-size:14px;">
                    <strong>Important:</strong> Please arrive 15 minutes before the event starts. 
                    {$entryInstruction}
                </p>
            </div>

            <p style="font-size:16px;color:#333;margin:0 0 10px;">
                We look forward to seeing you there!
            </p>
            <p style="font-size:16px;color:#333;margin:0;">
                Best regards,<br>
                <strong style="color:#f97316;">The PawTech Team</strong>
            </p>
        </div>

        <!-- Footer -->
        <div style="background:#f9fafb;padding:20px;text-align:center;border-top:1px solid #e5e7eb;">
            <p style="margin:0;color:#6b7280;font-size:12px;">
                © 2026 PawTech. All rights reserved.<br>
                This email was sent because you registered for an event on our platform.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build plain text content for confirmation email
     */
    private function buildConfirmationEmailText(Participation $participation): string
    {
        $user = $participation->getUser();
        $event = $participation->getEvenement();

        $userName = $user->getPrenom() ?? 'Participant';
        $eventTitle = $event->getTitre();
        $eventDate = $event->getDateDebut()?->format('l, F j, Y at H:i') ?? 'TBD';
        $eventLocation = $event->getLieu();
        $eventCity = $event->getVille();
        $eventType = $event->getType();
        $participationId = $participation->getId();

        return <<<TEXT
Hello {$userName},

Great news! Your registration has been confirmed for:

EVENT: {$eventTitle}
DATE: {$eventDate}
LOCATION: {$eventLocation}, {$eventCity}
TYPE: {$eventType}

TICKET NUMBER: #{$participationId}

Please arrive 15 minutes before the event starts.
Present your confirmation email at the entrance for check-in.

We look forward to seeing you there!

Best regards,
The PawTech Team
TEXT;
    }

    private function formatEndDate(string $endDate): string
    {
        if (empty($endDate)) {
            return '';
        }
        return "<br><span style='color:#666;font-size:14px;'>Until: {$endDate}</span>";
    }

    private function formatDescription(string $description): string
    {
        if (empty($description)) {
            return '';
        }
        return "<p style='margin:15px 0 0;color:#666;font-size:14px;'><em>{$description}</em></p>";
    }
}

