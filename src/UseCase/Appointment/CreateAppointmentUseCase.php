<?php

namespace App\UseCase\Appointment;

use App\DTO\Appointment\CreateAppointmentDTO;
use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use App\Repository\DoctorRepository;
use App\Repository\PatientRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CreateAppointmentUseCase
{
    public function __construct(
        private AppointmentRepository $appointmentRepository,
        private DoctorRepository $doctorRepository,
        private PatientRepository $patientRepository,
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

        $scheduledAt = \DateTime::createFromFormat('Y-m-d H:i:s', $dto->scheduledAt);

        if ($scheduledAt < new \DateTime()) {
            throw new BadRequestHttpException('Cannot schedule appointment in the past');
        }

        // Regra: conflito de horário (janela de 30 minutos)
        if ($this->appointmentRepository->hasConflict($doctor, $scheduledAt)) {
            throw new BadRequestHttpException('Doctor already has an appointment in this time slot');
        }

        // Regra: limite diário de consultas
        $count = $this->appointmentRepository->countByDoctorAndDate($doctor, $scheduledAt);
        if ($count >= $doctor->getMaxAppointmentsPerDay()) {
            throw new BadRequestHttpException(
                "Doctor has reached the maximum of {$doctor->getMaxAppointmentsPerDay()} appointments for this day"
            );
        }

        $appointment = new Appointment();
        $appointment->setDoctor($doctor);
        $appointment->setPatient($patient);
        $appointment->setScheduledAt($scheduledAt);
        $appointment->setNotes($dto->notes);

        $this->appointmentRepository->save($appointment);

        return $appointment;
    }
}
