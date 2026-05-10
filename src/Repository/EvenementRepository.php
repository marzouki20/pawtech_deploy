<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * Find upcoming active events for homepage
     * @return Evenement[]
     */
    public function findUpcomingEvents(int $limit = 3): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.statut IN (:statuts)')
            ->andWhere('e.dateDebut >= :now')
            ->setParameter('statuts', ['PLANIFIE', 'EN_COURS'])
            ->setParameter('now', new \DateTime())
            ->orderBy('e.dateDebut', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active events with optional filters
     * @return Evenement[]
     */
    public function findActiveWithFilters(?string $type = null, ?string $ville = null, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.statut IN (:statuts)')
            ->setParameter('statuts', ['PLANIFIE', 'EN_COURS'])
            ->orderBy('e.dateDebut', 'ASC');

        if ($type) {
            $qb->andWhere('e.type = :type')
               ->setParameter('type', $type);
        }

        if ($ville) {
            $qb->andWhere('e.ville LIKE :ville')
               ->setParameter('ville', '%' . $ville . '%');
        }

        if ($search) {
            $qb->andWhere('e.titre LIKE :q OR e.description LIKE :q OR e.type LIKE :q OR e.ville LIKE :q OR e.lieu LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all unique cities from active events
     * @return string[]
     */
    public function findActiveCities(): array
    {
        return $this->createQueryBuilder('e')
            ->select('DISTINCT e.ville')
            ->where('e.statut IN (:statuts)')
            ->setParameter('statuts', ['PLANIFIE', 'EN_COURS'])
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Check if an event is active (can be viewed publicly)
     */
    public function isEventActive(Evenement $evenement): bool
    {
        return in_array($evenement->getStatut(), ['PLANIFIE', 'EN_COURS']);
    }

    /**
     * Find events with admin filters (search, type, statut, sort)
     * @return Evenement[]
     */
    public function findWithAdminFilters(?string $search = null, ?string $type = null, ?string $statut = null, string $sort = 'dateDebut_DESC'): array
    {
        $qb = $this->createQueryBuilder('e');

        // Search by titre, lieu, or ville
        if ($search) {
            $qb->andWhere('e.titre LIKE :q OR e.lieu LIKE :q OR e.ville LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        // Filter by type
        if ($type) {
            $qb->andWhere('e.type = :type')
               ->setParameter('type', $type);
        }

        // Filter by statut
        if ($statut) {
            $qb->andWhere('e.statut = :statut')
               ->setParameter('statut', $statut);
        }

        // Sort
        [$sortField, $sortDir] = explode('_', $sort);
        $qb->orderBy('e.' . $sortField, $sortDir);

        return $qb->getQuery()->getResult();
    }
}
