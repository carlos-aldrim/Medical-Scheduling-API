<?php

namespace App\ValueObject;

use InvalidArgumentException;

/**
 * Value Object representing a Brazilian CPF (Cadastro de Pessoas Físicas).
 *
 * Encapsulates normalisation (strips formatting) and validation (check
 * digits + rejection of repeated-digit sequences) so this logic lives
 * once in the domain instead of being duplicated across DTOs/UseCases.
 */
final class Cpf
{
    /** Digits only, always 11 characters. */
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

        // Reject all-same-digit sequences (00000000000, 11111111111 …)
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

    /** Raw 11-digit representation, e.g. "12345678909". */
    public function value(): string
    {
        return $this->value;
    }

    /** Formatted representation, e.g. "123.456.789-09". */
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
