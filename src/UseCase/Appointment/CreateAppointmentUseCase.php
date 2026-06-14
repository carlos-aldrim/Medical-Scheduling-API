<?php

namespace App\UseCase\Appointment;

use App\DTO\Appointment\CreateAppointmentDTO;
use App\Entity\Appointment;
use App\Event\AppointmentCreatedEvent;
use App\Repository\AppointmentRepository;
use App\Repository\DoctorRepository;
use App\Repository\PatientRepository;
use App\ValueObject\AppointmentSlot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateAppointmentUseCase
{
    public function __construct(
        private AppointmentRepository  $appointmentRepository,
        private DoctorRepository       $doctorRepository,
        private PatientRepository      $patientRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface    $eventBus,
    ) {}

    public function execute(CreateAppointmentDTO $dto): Appointment
    {
        $doctor = $this->doctorRepository->find($dto->doctorId);
        if (!$doctor) {
            throw new NotFoundHttpException('Doctor not found');
        }

        if (!$doctor->isActive()) {
            throw new BadRequestHttpException('Doctor is not active');
        }

        $patient = $this->patientRepository->find($dto->patientId);
        if (!$patient) {
            throw new NotFoundHttpException('Patient not found');
        }

        if (!$patient->isActive()) {
            throw new BadRequestHttpException('Patient is not active');
        }

        try {
            $slot = AppointmentSlot::fromString($dto->scheduledAt)->ensureIsInTheFuture();
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        }

        $appointment = $this->entityManager->wrapInTransaction(
            function () use ($doctor, $patient, $slot, $dto): Appointment {

                if ($this->appointmentRepository->hasConflict($doctor, $slot, lockForUpdate: true)) {
                    throw new BadRequestHttpException('Doctor already has an appointment in this time slot');
                }

                $count = $this->appointmentRepository->countByDoctorAndDate($doctor, $slot);
                if ($count >= $doctor->getMaxAppointmentsPerDay()) {
                    throw new BadRequestHttpException(
                        "Doctor has reached the maximum of {$doctor->getMaxAppointmentsPerDay()} appointments for this day"
                    );
                }

                $appointment = new Appointment();
                $appointment->setDoctor($doctor);
                $appointment->setPatient($patient);
                $appointment->setScheduledAt($slot->value());
                $appointment->setNotes($dto->notes);

                $this->appointmentRepository->save($appointment, flush: false);

                return $appointment;
            }
        );

        $this->eventBus->dispatch(AppointmentCreatedEvent::fromAppointment($appointment));

        return $appointment;
    }
}
