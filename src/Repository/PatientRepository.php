<?php

namespace App\Repository;

use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PatientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Patient::class);
    }

    public function save(Patient $patient, bool $flush = true): void
    {
        $this->getEntityManager()->persist($patient);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Patient $patient, bool $flush = true): void
    {
        $this->getEntityManager()->remove($patient);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
