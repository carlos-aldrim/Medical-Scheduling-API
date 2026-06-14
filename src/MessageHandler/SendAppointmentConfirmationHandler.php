<?php

namespace App\MessageHandler;

use App\Event\AppointmentCreatedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Simulates sending a confirmation e-mail/SMS to the patient when a new
 * appointment is created.
 *
 * In a real system this would call a Mailer/SMS gateway. Here it just logs
 * the notification, demonstrating the event-driven flow: the UseCase only
 * dispatches the event and has no knowledge of how (or whether) the patient
 * is notified.
 *
 * Because this handler is registered on the `App\Event\AppointmentCreatedEvent`
 * message, it is invoked by the configured transport (see
 * config/packages/messenger.yaml). Routing the message to the `async`
 * transport (RabbitMQ/Redis) makes notification delivery non-blocking with
 * respect to the HTTP request/response cycle.
 */
#[AsMessageHandler]
final class SendAppointmentConfirmationHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(AppointmentCreatedEvent $event): void
    {
        // Simulated e-mail notification.
        $this->logger->info(
            '[notification:email] Appointment confirmation sent to patient {patient} for appointment {appointment} with Dr. {doctor} on {scheduledAt}',
            [
                'patient'     => $event->patientName,
                'appointment' => $event->appointmentId,
                'doctor'      => $event->doctorName,
                'scheduledAt' => $event->scheduledAt,
            ],
        );

        // Simulated SMS notification.
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
