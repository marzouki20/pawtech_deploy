<?php

namespace App\Service;

use App\Repository\ParticipationRepository;
use App\Entity\User;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DonorPredictionService
{
    private HttpClientInterface $httpClient;
    private ParticipationRepository $participationRepository;
    private string $apiUrl = 'http://127.0.0.1:8003';

    public function __construct(
        HttpClientInterface $httpClient,
        ParticipationRepository $participationRepository
    ) {
        $this->httpClient = $httpClient;
        $this->participationRepository = $participationRepository;
    }

    public function getUserStats(User $user): array
    {
        $participations = $this->participationRepository->findBy(['user' => $user]);
        
        $stats = [
            'user_id' => $user->getId(),
            'total_participations' => count($participations),
            'donation_events' => 0,
            'charity_events' => 0,
            'adoption_events' => 0,
            'vaccination_events' => 0,
            'days_since_last' => 365,
            'avg_satisfaction' => 0
        ];

        $confirmedCount = 0;
        $latestDate = null;

        foreach ($participations as $participation) {
            $statut = strtoupper($participation->getStatut() ?? '');
            
            // Accept both CONFIRME and CONFIRMEE
            if (!str_contains($statut, 'CONFIRM')) {
                continue;
            }
            
            $confirmedCount++;
            $event = $participation->getEvenement();
            
            if ($event) {
                $type = strtoupper($event->getType() ?? '');
                $title = strtoupper($event->getTitre() ?? '');
                
                // Check both type and title for keywords
                $combined = $type . ' ' . $title;
                
                if (str_contains($combined, 'DON') || str_contains($combined, 'COLLECTE')) {
                    $stats['donation_events']++;
                }
                if (str_contains($combined, 'CHARIT') || str_contains($combined, 'SENSIB')) {
                    $stats['charity_events']++;
                }
                if (str_contains($combined, 'ADOPT')) {
                    $stats['adoption_events']++;
                }
                if (str_contains($combined, 'VACCIN')) {
                    $stats['vaccination_events']++;
                }

                $eventDate = $event->getDateDebut();
                if ($eventDate && ($latestDate === null || $eventDate > $latestDate)) {
                    $latestDate = $eventDate;
                }
            }
        }

        if ($latestDate) {
            $now = new \DateTime();
            $stats['days_since_last'] = $now->diff($latestDate)->days;
        }

        $stats['total_participations'] = $confirmedCount;
        
        // Satisfaction score: base + bonus for donation participation
        // Users who participate in donation events typically have higher satisfaction
        $baseSatisfaction = $confirmedCount > 0 ? min(5, 2.5 + ($confirmedCount * 0.3)) : 0;
        $donationBonus = $stats['donation_events'] * 0.4; // Reward donation participation
        $stats['avg_satisfaction'] = min(5, $baseSatisfaction + $donationBonus);

        return $stats;
    }

    public function predictForUser(User $user): ?array
    {
        try {
            $stats = $this->getUserStats($user);
            
            $response = $this->httpClient->request('POST', $this->apiUrl . '/donor/predict', [
                'json' => $stats,
                'timeout' => 10
            ]);

            $data = $response->toArray();
            
            if ($data['ok'] ?? false) {
                return [
                    'user_id' => $user->getId(),
                    'user_name' => $user->getFullName(),
                    'user_email' => $user->getEmail(),
                    'propensity_score' => $data['propensity_score'],
                    'category' => $data['category'],
                    'recommendation' => $data['recommendation'],
                    'stats' => $stats
                ];
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function predictForAllUsers(array $users): array
    {
        $results = [];
        
        foreach ($users as $user) {
            $prediction = $this->predictForUser($user);
            if ($prediction) {
                $results[] = $prediction;
            }
        }

        usort($results, fn($a, $b) => $b['propensity_score'] <=> $a['propensity_score']);

        return $results;
    }

    public function getModelInfo(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiUrl . '/donor/model-info', [
                'timeout' => 10
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            return null;
        }
    }
}
