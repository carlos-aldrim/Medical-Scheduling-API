<?php

namespace App\DTO\Specialty;

use Symfony\Component\Validator\Constraints as Assert;

class CreateSpecialtyDTO
{
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Name must be at least {{ limit }} characters',
        maxMessage: 'Name cannot exceed {{ limit }} characters'
    )]
    #[Assert\Regex(
        pattern: '/^[\p{L}\s\'\-]+$/u',
        message: 'Name can only contain letters, spaces, hyphens and apostrophes'
    )]
    public string $name;

    #[Assert\NotBlank(message: 'Description is required')]
    #[Assert\Length(
        min: 10,
        max: 500,
        minMessage: 'Description must be at least {{ limit }} characters',
        maxMessage: 'Description cannot exceed {{ limit }} characters'
    )]
    #[Assert\Regex(
        pattern: '/^\S/u',
        message: 'Description cannot start with whitespace'
    )]
    public string $description;
}
