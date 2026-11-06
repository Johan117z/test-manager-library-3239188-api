<?php

namespace App\Application\Shared;

use App\Domain\Shared\BusinessRuleViolation;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Inclusive date range over instants (team decision #6): loaned_at >= from 00:00 UTC and
 * loaned_at < (to + 1 day) 00:00 UTC. End before start -> 422 INVALID_DATE_RANGE (CA-10.2).
 */
final class DateRange
{
    private function __construct(
        public readonly ?DateTimeImmutable $from,
        public readonly ?DateTimeImmutable $toExclusive,
    ) {
    }

    public static function of(?string $from, ?string $to): self
    {
        $start = $from === null ? null : self::day($from, 'from');
        $end = $to === null ? null : self::day($to, 'to');
        if ($start !== null && $end !== null && $end < $start) {
            throw new BusinessRuleViolation('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la inicial.', [
                ['field' => 'to', 'issue' => 'anterior a from'],
            ]);
        }

        return new self($start, $end?->modify('+1 day'));
    }

    private static function day(string $value, string $field): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw BusinessRuleViolation::validation($field, 'debe ser una fecha AAAA-MM-DD');
        }

        return $date;
    }
}
