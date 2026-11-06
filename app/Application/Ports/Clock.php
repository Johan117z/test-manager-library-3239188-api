<?php

namespace App\Application\Ports;

use DateTimeImmutable;

interface Clock
{
    /** Current instant in UTC. */
    public function now(): DateTimeImmutable;
}
