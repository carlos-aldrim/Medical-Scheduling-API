<?php

namespace App\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class AppointmentSlot
{
    private const FORMAT = 'Y-m-d H:i:s';

    public const CONFLICT_WINDOW_MINUTES = 29;

    private readonly DateTimeImmutable $scheduledAt;

    private function __construct(DateTimeImmutable $scheduledAt)
    {
        $this->scheduledAt = $scheduledAt;
    }

    public static function fromString(string $scheduledAt): self
    {
        $parsed = DateTimeImmutable::createFromFormat(
            self::FORMAT,
            $scheduledAt,
            new DateTimeZone('UTC'),
        );

        if ($parsed === false) {
            throw new InvalidArgumentException('Invalid date format for scheduledAt');
        }

        return new self($parsed);
    }

    public static function fromDateTime(\DateTimeInterface $scheduledAt): self
    {
        return new self(DateTimeImmutable::createFromInterface($scheduledAt));
    }

    public function ensureIsInTheFuture(?DateTimeImmutable $now = null): self
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($this->scheduledAt <= $now) {
            throw new InvalidArgumentException('Cannot schedule appointment in the past');
        }

        $twoYearsFromNow = $now->modify('+2 years');
        if ($this->scheduledAt > $twoYearsFromNow) {
            throw new InvalidArgumentException('Cannot schedule appointment more than 2 years in the future');
        }

        return $this;
    }

    public function value(): DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function windowStart(int $minutes = self::CONFLICT_WINDOW_MINUTES): DateTimeImmutable
    {
        return $this->scheduledAt->modify("-{$minutes} minutes");
    }

    public function windowEnd(int $minutes = self::CONFLICT_WINDOW_MINUTES): DateTimeImmutable
    {
        return $this->scheduledAt->modify("+{$minutes} minutes");
    }

    public function dayStart(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(
            self::FORMAT,
            $this->scheduledAt->format('Y-m-d') . ' 00:00:00',
            new DateTimeZone('UTC'),
        );
    }

    public function dayEnd(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(
            self::FORMAT,
            $this->scheduledAt->format('Y-m-d') . ' 23:59:59',
            new DateTimeZone('UTC'),
        );
    }

    public function overlaps(self $other, int $minutes = self::CONFLICT_WINDOW_MINUTES): bool
    {
        return $other->value() >= $this->windowStart($minutes)
            && $other->value() <= $this->windowEnd($minutes);
    }

    public function equals(self $other): bool
    {
        return $this->scheduledAt->getTimestamp() === $other->scheduledAt->getTimestamp();
    }

    public function format(string $format = self::FORMAT): string
    {
        return $this->scheduledAt->format($format);
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
