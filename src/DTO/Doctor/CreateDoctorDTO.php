<?php

namespace App\DTO\Doctor;

use App\Validator\CrmConstraint;
use Symfony\Component\Validator\Constraints as Assert;

class CreateDoctorDTO
{
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Name must be at least {{ limit }} characters',
        maxMessage: 'Name cannot exceed {{ limit }} characters'
    )]
    #[Assert\Regex(
        pattern: '/^[\p{L}\s\'\-\.]+$/u',
        message: 'Name can only contain letters, spaces, hyphens, apostrophes and dots'
    )]
    public string $name;

    #[Assert\NotBlank(message: 'CRM is required')]
    #[Assert\Length(
        min: 4,
        max: 15,
        minMessage: 'CRM must be at least {{ limit }} characters',
        maxMessage: 'CRM cannot exceed {{ limit }} characters'
    )]
    #[CrmConstraint]
    public string $crm;

    #[Assert\NotBlank(message: 'Specialty ID is required')]
    #[Assert\Uuid(message: 'Specialty ID must be a valid UUID')]
    public string $specialtyId;

    #[Assert\NotNull(message: 'Max appointments per day is required')]
    #[Assert\Range(
        min: 1,
        max: 20,
        notInRangeMessage: 'Max appointments per day must be between {{ min }} and {{ max }}'
    )]
    #[Assert\Type(
        type: 'integer',
        message: 'Max appointments per day must be an integer'
    )]
    public int $maxAppointmentsPerDay = 10;
}
