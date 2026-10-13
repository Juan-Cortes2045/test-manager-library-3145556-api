<?php

namespace App\Application\Ports;

/** UUID v4 generator port: identifiers are produced by the application, never by a column DEFAULT. */
interface IdGenerator
{
    public function next(): string;
}
