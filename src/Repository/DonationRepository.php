<?php

namespace App\Repository;

use App\Entity\Donation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DonationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Donation::class);
    }

    /**
     * Search donations by donor, email or reference
     * @return Donation[]
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('d')
            ->where('LOWER(d.donateur) LIKE :query')
            ->orWhere('LOWER(d.email) LIKE :query')
            ->orWhere('LOWER(d.reference) LIKE :query')
            ->setParameter('query', '%' . mb_strtolower($query) . '%')
            ->orderBy('d.date', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}