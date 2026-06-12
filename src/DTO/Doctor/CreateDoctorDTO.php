<?php

namespace App\DTO\Doctor;

use Symfony\Component\Validator\Constraints as Assert;

class CreateDoctorDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    public string $name;

    #[Assert\NotBlank]
    #[Assert\Length(min: 4, max: 10)]
    public string $crm;

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $specialtyId;

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(value: 20)]
    public int $maxAppointmentsPerDay = 10;
}
