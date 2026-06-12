<?php

namespace App\DTO\Specialty;

use Symfony\Component\Validator\Constraints as Assert;

class CreateSpecialtyDTO
{
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(min: 3, max: 255)]
    public string $name;

    #[Assert\NotBlank(message: 'Description is required')]
    #[Assert\Length(min: 3, max: 255)]
    public string $description;
}
