<?php

namespace App\Service;

use App\Entity\Alert;
use App\Repository\AlertRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    private $alertRepository;
    private $entityManager;

    public function __construct(
        AlertRepository $alertRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->alertRepository = $alertRepository;
        $this->entityManager = $entityManager;
    }

    public function getNotifications(): array
    {
        return $this->alertRepository->findAll();
    }

    public function markAsRead(int $id): void
    {
        $alert = $this->alertRepository->find($id);
        if ($alert) {
            $alert->setStatut('read');
            $this->entityManager->persist($alert);
            $this->entityManager->flush();
        }
    }

    public function markAsUnread(int $id): void
    {
        $alert = $this->alertRepository->find($id);
        if ($alert) {
            $alert->setStatut('unread');
            $this->entityManager->persist($alert);
            $this->entityManager->flush();
        }
    }

    public function getNotificationById(int $id): ?Alert
    {
        return $this->alertRepository->find($id);
    }
}
