<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * Search commandes by id, total or date (format dd/mm/YYYY)
     * @return Commande[]
     */
    public function search(string $q): array
    {
        $q = trim($q);
        $qb = $this->createQueryBuilder('c');

        if ($q === '') {
            return $qb->orderBy('c.id', 'DESC')->getQuery()->getResult();
        }

        // If numeric, search by id or total
        if (ctype_digit($q)) {
            $qb->where('c.id = :id OR c.total = :total')
               ->setParameter('id', (int) $q)
               ->setParameter('total', (float) $q);
        } else {
            // Try parse date in dd/mm/YYYY format
            $date = \DateTime::createFromFormat('d/m/Y', $q);
            if ($date !== false) {
                // c.date is a DATE column so comparing DateTime should work
                $qb->where('c.date = :date')
                   ->setParameter('date', $date);
            } else {
                // No sensible match: return empty result
                $qb->where('1 = 0');
            }
        }

        return $qb->orderBy('c.id', 'DESC')->getQuery()->getResult();
    }
}
