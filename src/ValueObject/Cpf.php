<?php

namespace App\ValueObject;

use InvalidArgumentException;

final class Cpf
{
    private readonly string $value;

    public function __construct(string $cpf)
    {
        $digits = preg_replace('/\D/', '', $cpf) ?? '';

        if (!self::isValid($digits)) {
            throw new InvalidArgumentException("The CPF \"{$cpf}\" is not valid.");
        }

        $this->value = $digits;
    }

    public static function fromString(string $cpf): self
    {
        return new self($cpf);
    }

    public static function isValid(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf) ?? '';

        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * ($t + 1 - $i);
            }
            $remainder = (10 * $sum) % 11 % 10;

            if ((int) $cpf[$t] !== $remainder) {
                return false;
            }
        }

        return true;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function formatted(): string
    {
        return sprintf(
            '%s.%s.%s-%s',
            substr($this->value, 0, 3),
            substr($this->value, 3, 3),
            substr($this->value, 6, 3),
            substr($this->value, 9, 2),
        );
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
