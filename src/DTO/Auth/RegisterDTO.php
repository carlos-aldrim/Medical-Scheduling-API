<?php

namespace App\DTO\Auth;

use App\Enum\UserRole;
use Symfony\Component\Validator\Constraints as Assert;

class RegisterDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required')]
        #[Assert\Length(
            min: 2,
            max: 255,
            minMessage: 'Name must be at least {{ limit }} characters',
            maxMessage: 'Name cannot exceed {{ limit }} characters'
        )]
        #[Assert\Regex(
            pattern: '/^[\p{L}\s\'\-\.]+$/u',
            message: 'Name can only contain letters, spaces, hyphens, apostrophes and dots'
        )]
        public readonly string $name,

        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(mode: 'html5', message: 'Email "{{ value }}" is not a valid email address')]
        #[Assert\Length(max: 180, maxMessage: 'Email cannot exceed {{ limit }} characters')]
        public readonly string $email,

        #[Assert\NotBlank(message: 'Password is required')]
        #[Assert\Length(min: 8, max: 72,
            minMessage: 'Password must be at least {{ limit }} characters',
            maxMessage: 'Password cannot exceed {{ limit }} characters'
        )]
        #[Assert\Regex(pattern: '/[A-Z]/', message: 'Password must contain at least one uppercase letter')]
        #[Assert\Regex(pattern: '/[a-z]/', message: 'Password must contain at least one lowercase letter')]
        #[Assert\Regex(pattern: '/\d/',    message: 'Password must contain at least one number')]
        public readonly string $password,

        #[Assert\NotBlank(message: 'Role is required')]
        #[Assert\Choice(
            choices: [
                UserRole::Admin->value,
                UserRole::Doctor->value,
                UserRole::Receptionist->value,
            ],
            message: 'Role "{{ value }}" is not valid. Allowed: {{ choices }}'
        )]
        public readonly string $role = UserRole::Receptionist->value,
    ) {}
}
