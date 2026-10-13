<?php

namespace Tests\Fakes;

use App\Application\Ports\IdGenerator;

final class SequentialIds implements IdGenerator
{
    private int $n = 0;

    public function next(): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', ++$this->n);
    }
}
