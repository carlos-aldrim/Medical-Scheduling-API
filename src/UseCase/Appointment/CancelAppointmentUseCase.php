<?php

namespace App\UseCase\Appointment;

use App\Entity\Appointment;
use App\Enum\AppointmentStatus;
use App\Event\AppointmentCancelledEvent;
use App\Repository\AppointmentRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class CancelAppointmentUseCase
{
    public function __construct(
        private AppointmentRepository $appointmentRepository,
        private MessageBusInterface   $eventBus,
    ) {}

    public function execute(string $id): Appointment
    {
        $appointment = $this->appointmentRepository->find($id);
        if (!$appointment) {
            throw new NotFoundHttpException('Appointment not found');
        }

        $status = $appointment->getStatus();

        if (!$status->isCancellable()) {
            $message = match ($status) {
                AppointmentStatus::Cancelled => 'Appointment is already cancelled',
                AppointmentStatus::Completed => 'Cannot cancel a completed appointment',
                default                      => "Cannot cancel an appointment with status \"{$status->value}\"",
            };

            throw new BadRequestHttpException($message);
        }

        $appointment->cancel();
        $this->appointmentRepository->save($appointment);

        $this->eventBus->dispatch(AppointmentCancelledEvent::fromAppointment($appointment));

        return $appointment;
    }
}
