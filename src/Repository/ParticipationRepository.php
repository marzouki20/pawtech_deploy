<?php

namespace App\Repository;

use App\Entity\Participation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    /**
     * Find participations with admin filters (search, statut, sort)
     * @return Participation[]
     */
    public function findWithAdminFilters(?string $search = null, ?string $statut = null, string $sort = 'dateParticipation_desc'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->leftJoin('p.evenement', 'e')
            ->addSelect('u', 'e');

        // Search by user name, email, or event title
        if ($search) {
            $qb->andWhere('u.nom LIKE :q OR u.prenom LIKE :q OR u.email LIKE :q OR e.titre LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        // Filter by statut
        if ($statut) {
            $qb->andWhere('p.statut = :statut')
               ->setParameter('statut', $statut);
        }

        // Sort
        [$sortField, $sortDir] = explode('_', $sort) + ['dateParticipation', 'desc'];
        $sortMapping = [
            'dateParticipation' => 'p.dateParticipation',
            'statut' => 'p.statut',
            'user' => 'u.nom',
            'evenement' => 'e.titre',
        ];
        $sortColumn = $sortMapping[$sortField] ?? 'p.dateParticipation';
        $qb->orderBy($sortColumn, $sortDir === 'asc' ? 'ASC' : 'DESC');

        return $qb->getQuery()->getResult();
    }
}
