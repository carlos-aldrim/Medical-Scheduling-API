<?php

namespace App\Event;

use App\Entity\Appointment;

final class AppointmentCancelledEvent
{
    private function __construct(
        public readonly string $appointmentId,
        public readonly string $doctorId,
        public readonly string $doctorName,
        public readonly string $patientId,
        public readonly string $patientName,
        public readonly string $scheduledAt,
    ) {}

    public static function fromAppointment(Appointment $appointment): self
    {
        return new self(
            appointmentId: (string) $appointment->getId(),
            doctorId:      (string) $appointment->getDoctor()?->getId(),
            doctorName:    (string) $appointment->getDoctor()?->getName(),
            patientId:     (string) $appointment->getPatient()?->getId(),
            patientName:   (string) $appointment->getPatient()?->getName(),
            scheduledAt:   $appointment->getScheduledAt()?->format('Y-m-d H:i:s') ?? '',
        );
    }
}
