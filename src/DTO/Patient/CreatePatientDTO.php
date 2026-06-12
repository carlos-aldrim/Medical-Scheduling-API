<?php

namespace App\DTO\Patient;

use Symfony\Component\Validator\Constraints as Assert;

class CreatePatientDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    public string $name;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 11)]
    public string $cpf;

    #[Assert\NotBlank]
    #[Assert\Date(message: 'Birth date must be in format Y-m-d')]
    public string $birthDate;

    #[Assert\Length(max: 20)]
    public ?string $phone = null;
}
