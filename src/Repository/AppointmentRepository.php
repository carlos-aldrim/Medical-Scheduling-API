<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Doctor;
use App\Enum\AppointmentStatus;
use App\ValueObject\AppointmentSlot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
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

    public function findWithFilters(
        array  $filters  = [],
        int    $limit    = 20,
        int    $offset   = 0,
        string $orderBy  = 'scheduledAt',
        string $order    = 'ASC',
    ): array {
        $limit  = min(max(1, $limit), 100);
        $offset = max(0, $offset);

        $allowedOrderBy = ['scheduledAt', 'createdAt', 'status'];
        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'scheduledAt';
        }
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.doctor', 'd')
            ->leftJoin('a.patient', 'p')
            ->addSelect('d', 'p');

        if (!empty($filters['status'])) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['date'])) {
            $utc   = new \DateTimeZone('UTC');
            $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $filters['date'] . ' 00:00:00', $utc);
            $end   = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $filters['date'] . ' 23:59:59', $utc);

            if ($start && $end) {
                $qb->andWhere('a.scheduledAt BETWEEN :dateStart AND :dateEnd')
                   ->setParameter('dateStart', $start)
                   ->setParameter('dateEnd',   $end);
            }
        }

        if (!empty($filters['doctorId'])) {
            $qb->andWhere('a.doctor = :doctorId')
               ->setParameter('doctorId', $filters['doctorId']);
        }

        if (!empty($filters['patientId'])) {
            $qb->andWhere('a.patient = :patientId')
               ->setParameter('patientId', $filters['patientId']);
        }

        $countQb = clone $qb;
        $total   = (int) $countQb->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        $results = $qb
            ->select('a', 'd', 'p')
            ->orderBy("a.{$orderBy}", $order)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data'   => $results,
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    public function hasConflict(
        Doctor          $doctor,
        AppointmentSlot $slot,
        ?string         $excludeId    = null,
        bool            $lockForUpdate = false,
    ): bool {
        $qb = $this->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.scheduledAt BETWEEN :start AND :end')
            ->andWhere('a.status != :cancelled')
            ->setParameter('doctor',    $doctor)
            ->setParameter('start',     $slot->windowStart())
            ->setParameter('end',       $slot->windowEnd())
            ->setParameter('cancelled', AppointmentStatus::Cancelled);

        if ($excludeId) {
            $qb->andWhere('a.id != :id')->setParameter('id', $excludeId);
        }

        $query = $qb->getQuery();

        if ($lockForUpdate) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        return count($query->getResult()) > 0;
    }

    public function countByDoctorAndDate(Doctor $doctor, AppointmentSlot $slot): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.doctor = :doctor')
            ->andWhere('a.scheduledAt BETWEEN :start AND :end')
            ->andWhere('a.status != :cancelled')
            ->setParameter('doctor',    $doctor)
            ->setParameter('start',     $slot->dayStart())
            ->setParameter('end',       $slot->dayEnd())
            ->setParameter('cancelled', AppointmentStatus::Cancelled)
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
