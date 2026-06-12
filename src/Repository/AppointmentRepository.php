<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Doctor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    public function save(Appointment $appointment, bool $flush = true): void
    {
        $this->getEntityManager()->persist($appointment);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Appointment $appointment, bool $flush = true): void
    {
        $this->getEntityManager()->remove($appointment);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // Verifica conflito de horário para o médico
    public function hasConflict(Doctor $doctor, \DateTimeInterface $scheduledAt, ?string $excludeId = null): bool
    {
        $start = \DateTime::createFromInterface($scheduledAt)->modify('-29 minutes');
        $end = \DateTime::createFromInterface($scheduledAt)->modify('+29 minutes');

        $qb = $this->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.scheduledAt BETWEEN :start AND :end')
            ->andWhere('a.status != :cancelled')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('cancelled', Appointment::STATUS_CANCELLED);

        if ($excludeId) {
            $qb->andWhere('a.id != :id')->setParameter('id', $excludeId);
        }

        return count($qb->getQuery()->getResult()) > 0;
    }

    // Conta consultas do médico em um dia específico
    public function countByDoctorAndDate(Doctor $doctor, \DateTimeInterface $date): int
    {
        $start = \DateTime::createFromFormat('Y-m-d H:i:s', $date->format('Y-m-d') . ' 00:00:00');
        $end = \DateTime::createFromFormat('Y-m-d H:i:s', $date->format('Y-m-d') . ' 23:59:59');

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.doctor = :doctor')
            ->andWhere('a.scheduledAt BETWEEN :start AND :end')
            ->andWhere('a.status != :cancelled')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('cancelled', Appointment::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByPatient(string $patientId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.patient = :patientId')
            ->setParameter('patientId', $patientId)
            ->orderBy('a.scheduledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByDoctor(string $doctorId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.doctor = :doctorId')
            ->setParameter('doctorId', $doctorId)
            ->orderBy('a.scheduledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
