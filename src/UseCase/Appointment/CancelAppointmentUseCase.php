<?php

namespace App\UseCase\Appointment;

use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CancelAppointmentUseCase
{
    public function __construct(
        private AppointmentRepository $appointmentRepository,
    ) {}

    public function execute(string $id): Appointment
    {
        $appointment = $this->appointmentRepository->find($id);
        if (!$appointment) {
            throw new NotFoundHttpException('Appointment not found');
        }

        if ($appointment->getStatus() === Appointment::STATUS_CANCELLED) {
            throw new BadRequestHttpException('Appointment is already cancelled');
        }

        if ($appointment->getStatus() === Appointment::STATUS_COMPLETED) {
            throw new BadRequestHttpException('Cannot cancel a completed appointment');
        }

        $appointment->cancel();
        $this->appointmentRepository->save($appointment);

        return $appointment;
    }
}
