<?php

namespace App\Application\Ports;

use DateTimeImmutable;

/**
 * Time source port (plan D-05): always UTC instants. Tests use a fixed clock to check overdue without waiting 14 days.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
