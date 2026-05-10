<?php

namespace App\Repository;

use App\Entity\Guest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Guest>
 */
class GuestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Guest::class);
    }

    /**
     * Find guests with admin filters (search, role, sort)
     * @return Guest[]
     */
    public function findWithAdminFilters(?string $search = null, ?string $role = null, string $sort = 'nom_asc'): array
    {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.evenement', 'e')
            ->addSelect('e');

        // Search by nom, prenom, email, organisation, or event title
        if ($search) {
            $qb->andWhere('g.nom LIKE :q OR g.prenom LIKE :q OR g.email LIKE :q OR g.organisation LIKE :q OR e.titre LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        // Filter by role
        if ($role) {
            $qb->andWhere('g.role = :role')
               ->setParameter('role', $role);
        }

        // Sort
        [$sortField, $sortDir] = explode('_', $sort) + ['nom', 'asc'];
        $sortMapping = [
            'nom' => 'g.nom',
            'role' => 'g.role',
            'organisation' => 'g.organisation',
            'evenement' => 'e.titre',
        ];
        $sortColumn = $sortMapping[$sortField] ?? 'g.nom';
        $qb->orderBy($sortColumn, $sortDir === 'asc' ? 'ASC' : 'DESC');

        return $qb->getQuery()->getResult();
    }
}
