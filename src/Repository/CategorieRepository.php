<?php

namespace App\Repository;

use App\Entity\Categorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class CategorieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categorie::class);
    }

  
    public function save(Categorie $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

  
    public function remove(Categorie $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchCategories(string $searchTerm): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.nom) LIKE LOWER(:searchTerm)')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    
    public function findOneByName(string $name): ?Categorie
    {
        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.nom) = LOWER(:name)')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

   
    public function findWithMinProducts(int $minProducts): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.produits', 'p')
            ->addSelect('COUNT(p.id) as productCount')
            ->groupBy('c.id')
            ->having('COUNT(p.id) >= :minProducts')
            ->setParameter('minProducts', $minProducts)
            ->orderBy('productCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

  
    public function findEmptyCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.produits', 'p')
            ->addSelect('COUNT(p.id) as productCount')
            ->groupBy('c.id')
            ->having('COUNT(p.id) = 0')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

  
    public function countAll(): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

  
    public function findMostPopular(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.produits', 'p')
            ->addSelect('COUNT(p.id) as productCount')
            ->groupBy('c.id')
            ->orderBy('productCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

  
    public function findAllWithProducts(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.produits', 'p')
            ->addSelect('p')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    
    public function advancedSearch(?string $name = null, ?int $minProducts = null, ?int $maxProducts = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.produits', 'p')
            ->addSelect('COUNT(p.id) as productCount')
            ->groupBy('c.id');

        if ($name) {
            $qb->andWhere('LOWER(c.nom) LIKE LOWER(:name)')
               ->setParameter('name', '%' . $name . '%');
        }

        if ($minProducts !== null) {
            $qb->andHaving('COUNT(p.id) >= :minProducts')
               ->setParameter('minProducts', $minProducts);
        }

        if ($maxProducts !== null) {
            $qb->andHaving('COUNT(p.id) <= :maxProducts')
               ->setParameter('maxProducts', $maxProducts);
        }

        $qb->orderBy('c.nom', 'ASC');

        return $qb->getQuery()->getResult();
    }

  
    public function findPaginated(int $page = 1, int $perPage = 50): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.nom', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

   
    public function findByProductCount(string $filter): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.produits', 'p')
            ->addSelect('COUNT(p.id) as productCount')
            ->groupBy('c.id');
        
        switch ($filter) {
            case 'empty':
                $qb->having('COUNT(p.id) = 0');
                break;
            case '1-5':
                $qb->having('COUNT(p.id) BETWEEN 1 AND 5');
                break;
            case '6+':
                $qb->having('COUNT(p.id) >= 6');
                break;
        }
        
        $qb->orderBy('c.nom', 'ASC');
        
        return $qb->getQuery()->getResult();
    }




}