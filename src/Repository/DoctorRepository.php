<?php

namespace App\Repository;

use App\Entity\Doctor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DoctorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Doctor::class);
    }

    public function save(Doctor $doctor, bool $flush = true): void
    {
        $this->getEntityManager()->persist($doctor);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Doctor $doctor, bool $flush = true): void
    {
        $this->getEntityManager()->remove($doctor);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
