<?php

namespace App\Repository;

use App\Entity\Suivi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Suivi>
 */
class SuiviRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Suivi::class);
    }

    /**
     * Recherche les suivis par type (compatible avec le workshop)
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.type LIKE :type')
            ->setParameter('type', '%' . $type . '%')
            ->orderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les suivis par etat exact
     */
    public function findByEtat(string $etat): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.etat = :etat')
            ->setParameter('etat', $etat)
            ->orderBy('s.prochaineVisite', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne tous les suivis ordonnes par prochaine visite decroissante
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.prochaineVisite', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les suivis du mois en cours
     */
    public function countThisMonth(\DateTime $startOfMonth): int
    {
        $endOfMonth = clone $startOfMonth;
        $endOfMonth->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.prochaineVisite BETWEEN :start AND :end')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compter les suivis par etat
     */
    public function countByEtat(string $etat): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.etat = :etat')
            ->setParameter('etat', $etat)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calculer le taux de completion
     */
    public function getCompletionRate(): int
    {
        $total = $this->count([]);

        if ($total === 0) {
            return 0;
        }

        $completed = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.etat = :etat')
            ->setParameter('etat', 'Terminé')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Trouver les suivis a venir
     */
    public function findUpcoming(int $limit = 5): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('s')
            ->where('s.prochaineVisite >= :now')
            ->andWhere('s.etat IN (:etats)')
            ->setParameter('now', $now)
            ->setParameter('etats', ['Planifié', 'Planifie', 'En cours'])
            ->orderBy('s.prochaineVisite', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les suivis par niveau d'urgence (filtre en PHP via getter)
     */
    public function findByEmergencyLevel(string $emergencyLevel): array
    {
        $target = strtolower(trim($emergencyLevel));
        $all = $this->findAll();

        return array_values(array_filter($all, static function (Suivi $suivi) use ($target): bool {
            return strtolower((string) ($suivi->getEmergencyLevel() ?? '')) === $target;
        }));
    }

    public function save(Suivi $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Suivi $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

