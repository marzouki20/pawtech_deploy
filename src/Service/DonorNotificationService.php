<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class DonorNotificationService
{
    private DonorPredictionService $donorPredictionService;
    private UserRepository $userRepository;
    private MailerInterface $mailer;
    private string $mailerFrom;

    public function __construct(
        DonorPredictionService $donorPredictionService,
        UserRepository $userRepository,
        MailerInterface $mailer,
        string $mailerFrom
    ) {
        $this->donorPredictionService = $donorPredictionService;
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
        $this->mailerFrom = $mailerFrom;
    }

    public function notifyPotentialDonors(Evenement $event): array
    {
        $results = [
            'event' => $event->getTitre(),
            'emails_sent' => 0,
            'high_potential' => 0,
            'medium_potential' => 0,
            'recipients' => []
        ];

        // Only send for donation-type events
        $eventType = strtoupper($event->getType() ?? '');
        $eventTitle = strtoupper($event->getTitre() ?? '');
        $combined = $eventType . ' ' . $eventTitle;

        if (!str_contains($combined, 'DON') && !str_contains($combined, 'COLLECTE') && !str_contains($combined, 'CHARIT')) {
            return $results;
        }

        // Get all users and their predictions
        $users = $this->userRepository->findAll();
        
        foreach ($users as $user) {
            $prediction = $this->donorPredictionService->predictForUser($user);
            
            if (!$prediction) {
                continue;
            }

            // Only notify High Potential and Medium users
            if ($prediction['category'] === 'High Potential' || $prediction['category'] === 'Medium') {
                try {
                    $this->sendDonationEventEmail($user, $event, $prediction);
                    
                    $results['emails_sent']++;
                    $results['recipients'][] = [
                        'name' => $user->getFullName(),
                        'email' => $user->getEmail(),
                        'category' => $prediction['category'],
                        'score' => $prediction['propensity_score']
                    ];

                    if ($prediction['category'] === 'High Potential') {
                        $results['high_potential']++;
                    } else {
                        $results['medium_potential']++;
                    }
                } catch (\Exception $e) {
                    // Continue with other users if one fails
                }
            }
        }

        return $results;
    }

    private function sendDonationEventEmail($user, Evenement $event, array $prediction): void
    {
        $category = $prediction['category'];
        $score = $prediction['propensity_score'];

        // Personalized subject based on propensity
        if ($category === 'High Potential') {
            $subject = "Invitation to Support: " . $event->getTitre();
            $intro = "As one of our most valued community members, we wanted to personally invite you to";
        } else {
            $subject = "Donation Event Invitation: " . $event->getTitre();
            $intro = "Based on your engagement with PawTech, we thought you might be interested in";
        }

        $eventDate = $event->getDateDebut() ? $event->getDateDebut()->format('l, F j, Y') : 'Date TBA';
        $eventTime = $event->getDateDebut() ? $event->getDateDebut()->format('g:i A') : '';
        $location = $event->getLieu() ?? 'Location TBA';
        $city = $event->getVille() ?? '';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #f97316, #ea580c); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .event-card { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn { display: inline-block; background: #f97316; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        .badge { display: inline-block; background: #10b981; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🐾 PawTech</h1>
            <p>Making a difference for animals</p>
        </div>
        <div class="content">
            <p>Dear {$user->getPrenom()},</p>

            <p style="background:#fff7ed;border-left:4px solid #f97316;padding:10px 12px;border-radius:6px;">
                <strong>Note:</strong> This is an invitation email only. It is not a participation confirmation and does not include a QR ticket.
            </p>
            
            <p>{$intro} our upcoming donation event:</p>
            
            <div class="event-card">
                <h2 style="color: #f97316; margin-top: 0;">{$event->getTitre()}</h2>
                <p><strong>📅 Date:</strong> {$eventDate} {$eventTime}</p>
                <p><strong>📍 Location:</strong> {$location}, {$city}</p>
                <p><strong>🏷️ Type:</strong> {$event->getType()}</p>
                <p>{$event->getDescription()}</p>
            </div>
            
            <p>Your participation can make a real difference in the lives of animals in need. Every contribution, big or small, helps us continue our mission.</p>
            
            <center>
                <a href="http://127.0.0.1:8000/events/{$event->getId()}" class="btn">View Event Details</a>
            </center>
            
            <p style="margin-top: 30px;">Thank you for being part of the PawTech community!</p>
            
            <p>Warm regards,<br><strong>The PawTech Team</strong></p>
        </div>
        <div class="footer">
            <p>You received this email because our AI system identified you as someone who might be interested in supporting animal welfare.</p>
            <p>© 2026 PawTech - All rights reserved</p>
        </div>
    </div>
</body>
</html>
HTML;

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject($subject)
            ->html($html);

        $this->mailer->send($email);
    }
}
