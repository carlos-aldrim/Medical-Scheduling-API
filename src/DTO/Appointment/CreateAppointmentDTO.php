<?php

namespace App\DTO\Appointment;

use Symfony\Component\Validator\Constraints as Assert;

class CreateAppointmentDTO
{
    #[Assert\NotBlank(message: 'Doctor ID is required')]
    #[Assert\Uuid(message: 'Doctor ID must be a valid UUID')]
    public string $doctorId;

    #[Assert\NotBlank(message: 'Patient ID is required')]
    #[Assert\Uuid(message: 'Patient ID must be a valid UUID')]
    public string $patientId;

    #[Assert\NotBlank(message: 'Scheduled date is required')]
    #[Assert\DateTime(
        format: 'Y-m-d H:i:s',
        message: 'Scheduled date must be in format YYYY-MM-DD HH:MM:SS'
    )]
    public string $scheduledAt;

    #[Assert\Length(
        max: 2000,
        maxMessage: 'Notes cannot exceed {{ limit }} characters'
    )]
    #[Assert\Regex(
        pattern: '/^\S.*$/u',
        message: 'Notes cannot start with whitespace',
        match: true
    )]
    public ?string $notes = null;
}
