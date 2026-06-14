<?php

namespace App\MessageHandler;

use App\Event\AppointmentCreatedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendAppointmentConfirmationHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(AppointmentCreatedEvent $event): void
    {
        $this->logger->info(
            '[notification:email] Appointment confirmation sent to patient {patient} for appointment {appointment} with Dr. {doctor} on {scheduledAt}',
            [
                'patient'     => $event->patientName,
                'appointment' => $event->appointmentId,
                'doctor'      => $event->doctorName,
                'scheduledAt' => $event->scheduledAt,
            ],
        );

        $this->logger->info(
            '[notification:sms] SMS confirmation sent to patient {patient} ({patientId}) — appointment scheduled for {scheduledAt}',
            [
                'patient'   => $event->patientName,
                'patientId' => $event->patientId,
                'scheduledAt' => $event->scheduledAt,
            ],
        );
    }
}
