<?php

namespace Tests\Fakes;

use App\Application\Ports\Clock;
use DateTimeImmutable;

final class FixedClock implements Clock
{
    public function __construct(public DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
