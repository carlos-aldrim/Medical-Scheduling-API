<?php

namespace App\MessageHandler;

use App\Event\AppointmentCancelledEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Simulates sending a cancellation e-mail/SMS to the patient when an
 * appointment is cancelled.
 *
 * See {@see SendAppointmentConfirmationHandler} for the rationale behind
 * this event-driven, Messenger-based notification flow.
 */
#[AsMessageHandler]
final class SendAppointmentCancellationHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(AppointmentCancelledEvent $event): void
    {
        // Simulated e-mail notification.
        $this->logger->info(
            '[notification:email] Appointment cancellation sent to patient {patient} for appointment {appointment} with Dr. {doctor} (was scheduled for {scheduledAt})',
            [
                'patient'     => $event->patientName,
                'appointment' => $event->appointmentId,
                'doctor'      => $event->doctorName,
                'scheduledAt' => $event->scheduledAt,
            ],
        );

        // Simulated SMS notification.
        $this->logger->info(
            '[notification:sms] SMS cancellation notice sent to patient {patient} ({patientId}) — appointment for {scheduledAt} was cancelled',
            [
                'patient'   => $event->patientName,
                'patientId' => $event->patientId,
                'scheduledAt' => $event->scheduledAt,
            ],
        );
    }
}
