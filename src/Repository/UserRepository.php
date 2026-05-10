<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }


     /**
     * Find a user by their id
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User
    {
        return $this->find($id);
    }











    /**
     * @return User[]
     */
    public function search(?string $query, ?string $field = 'all'): array
    {
        $query = trim((string) $query);
        $qb = $this->createQueryBuilder('u');
        $field = $field ?: 'all';

        if ($field !== 'all') {
            $fieldMap = [
                'id' => 'u.id',
                'first_name' => 'u.prenom',
                'last_name' => 'u.nom',
                'email' => 'u.email',
                'phone' => 'u.telephone',
                'role' => 'u.role',
                'status' => 'u.status',
            ];

            if (isset($fieldMap[$field])) {
                if ($field === 'id' && ctype_digit($query)) {
                    $qb->andWhere($fieldMap[$field].' = :id')
                        ->setParameter('id', (int) $query);
                } else {
                    $qb->andWhere('LOWER('.$fieldMap[$field].') LIKE :q')
                        ->setParameter('q', '%'.mb_strtolower($query).'%');
                }

                return $qb->orderBy('u.id', 'DESC')->getQuery()->getResult();
            }
        }

        $or = $qb->expr()->orX();
        $or->add('LOWER(u.prenom) LIKE :q');
        $or->add('LOWER(u.nom) LIKE :q');
        $or->add('LOWER(u.email) LIKE :q');
        $or->add('LOWER(u.telephone) LIKE :q');
        $or->add('LOWER(u.role) LIKE :q');
        $or->add('LOWER(u.status) LIKE :q');
        if (ctype_digit($query)) {
            $or->add('u.id = :id');
            $qb->setParameter('id', (int) $query);
        }

        $qb->andWhere($or)
            ->setParameter('q', '%'.mb_strtolower($query).'%')
            ->orderBy('u.id', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return User[]
     */
    public function sortAll(?string $sortDir = 'asc', ?string $field = 'all'): array
    {
        $direction = strtolower((string) $sortDir) === 'desc' ? 'DESC' : 'ASC';
        $field = $field ?: 'id';
        $fieldMap = [
            'id' => 'u.id',
            'first_name' => 'u.prenom',
            'last_name' => 'u.nom',
            'email' => 'u.email',
            'phone' => 'u.telephone',
            'role' => 'u.role',
            'status' => 'u.status',
        ];
        $orderField = $fieldMap[$field] ?? 'u.id';

        return $this->createQueryBuilder('u')
            ->orderBy($orderField, $direction)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
     */
    public function findVeterinarians(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.role) = :role')
            ->setParameter('role', 'veterinaire')
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
