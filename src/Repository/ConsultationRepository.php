<?php

namespace App\Repository;

use App\Entity\Consultation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConsultationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consultation::class);
    }

    /**
     * Recherche les consultations par type OU par nom d'utilisateur
     */
    public function search(string $searchTerm): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->where('c.type LIKE :searchTerm')
            ->orWhere('u.nom LIKE :searchTerm')
            ->orWhere('u.prenom LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('c.date', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne toutes les consultations ordonnées par date décroissante
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.date', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les consultations par type exact
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.type = :type')
            ->setParameter('type', $type)
            ->orderBy('c.date', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de consultations ce mois-ci
     */
    public function countThisMonth(): int
    {
        try {
            $start = new \DateTime('first day of this month 00:00:00');
            $end = new \DateTime('last day of this month 23:59:59');
            
            return $this->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.date BETWEEN :start AND :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Vérifie si une date est disponible pour une consultation
     */
    public function isDateAvailable(\DateTimeInterface $date, ?int $excludeId = null): bool
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->where('c.date = :date')
            ->setParameter('date', $date);
        
        if ($excludeId !== null) {
            $queryBuilder
                ->andWhere('c.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }
        
        $result = $queryBuilder
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
        
        return count($result) === 0;
    }
}