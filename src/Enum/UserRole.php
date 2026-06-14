<?php

namespace App\Enum;

enum UserRole: string
{
    case Admin        = 'ROLE_ADMIN';
    case Doctor       = 'ROLE_DOCTOR';
    case Receptionist = 'ROLE_RECEPTIONIST';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin        => 'Administrator',
            self::Doctor       => 'Doctor',
            self::Receptionist => 'Receptionist',
        };
    }

    public function canManageScheduling(): bool
    {
        return match ($this) {
            self::Admin, self::Receptionist => true,
            default                         => false,
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
