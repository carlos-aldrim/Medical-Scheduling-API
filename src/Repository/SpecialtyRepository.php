<?php

namespace App\Repository;

use App\Entity\Specialty;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SpecialtyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Specialty::class);
    }

    public function save(Specialty $specialty, bool $flush = true): void
    {
        $this->getEntityManager()->persist($specialty);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Specialty $specialty, bool $flush = true): void
    {
        $this->getEntityManager()->remove($specialty);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
