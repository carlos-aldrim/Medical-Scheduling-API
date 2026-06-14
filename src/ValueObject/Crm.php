<?php

namespace App\ValueObject;

use InvalidArgumentException;

final class Crm
{
    private const VALID_STATES = [
        'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO',
        'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR',
        'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
    ];

    private readonly string $number;
    private readonly ?string $state;

    public function __construct(string $crm)
    {
        $normalised = strtoupper(trim($crm));

        [$number, $state] = self::parse($normalised);

        if ($number === null) {
            throw new InvalidArgumentException(
                "The CRM \"{$crm}\" is not valid. Expected formats: 12345, CRM12345, CRM-SP-12345."
            );
        }

        $this->number = $number;
        $this->state  = $state;
    }

    public static function fromString(string $crm): self
    {
        return new self($crm);
    }

    public static function isValid(string $crm): bool
    {
        return self::parse(strtoupper(trim($crm)))[0] !== null;
    }

    private static function parse(string $crm): array
    {
        $states = implode('|', self::VALID_STATES);

        $patterns = [
            '/^(\d{4,6})$/'                       => 'plain',
            '/^CRM(\d{4,6})$/'                    => 'crm',
            '/^CRM-(' . $states . ')-(\d{4,6})$/' => 'crm_state',
            '/^(' . $states . ')-(\d{4,6})$/'     => 'state',
        ];

        foreach ($patterns as $pattern => $kind) {
            if (!preg_match($pattern, $crm, $matches)) {
                continue;
            }

            return match ($kind) {
                'crm_state', 'state' => [$matches[2], $matches[1]],
                default              => [$matches[1], null],
            };
        }

        return [null, null];
    }

    public function number(): string
    {
        return $this->number;
    }

    public function state(): ?string
    {
        return $this->state;
    }

    public function formatted(): string
    {
        return $this->state !== null
            ? "CRM-{$this->state}-{$this->number}"
            : "CRM-{$this->number}";
    }

    public function value(): string
    {
        return $this->formatted();
    }

    public function equals(self $other): bool
    {
        return $this->number === $other->number && $this->state === $other->state;
    }

    public function __toString(): string
    {
        return $this->formatted();
    }
}
