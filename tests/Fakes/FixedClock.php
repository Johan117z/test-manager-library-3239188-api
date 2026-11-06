<?php

namespace Tests\Fakes;

use App\Application\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;

final class FixedClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $now = '2026-10-14T14:15:00Z')
    {
        $this->now = new DateTimeImmutable($now, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
