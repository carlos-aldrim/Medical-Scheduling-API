<?php

namespace App\DTO\Patient;

use App\Validator\CpfConstraint;
use Symfony\Component\Validator\Constraints as Assert;

class CreatePatientDTO
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

    #[Assert\NotBlank(message: 'CPF is required')]
    #[Assert\Regex(
        pattern: '/^\d{11}$/',
        message: 'CPF must contain exactly 11 digits (numbers only, no dots or dashes)'
    )]
    #[CpfConstraint]
    public string $cpf;

    #[Assert\NotBlank(message: 'Birth date is required')]
    #[Assert\Date(message: 'Birth date must be in format YYYY-MM-DD')]
    #[Assert\LessThan(
        value: 'today',
        message: 'Birth date must be in the past'
    )]
    #[Assert\GreaterThan(
        value: '1900-01-01',
        message: 'Birth date is not realistic (before 1900)'
    )]
    public string $birthDate;

    #[Assert\Length(
        max: 20,
        maxMessage: 'Phone cannot exceed {{ limit }} characters'
    )]
    #[Assert\Regex(
        pattern: '/^\+?[\d\s\(\)\-]{10,20}$/',
        message: 'Phone number format is invalid'
    )]
    public ?string $phone = null;
}
