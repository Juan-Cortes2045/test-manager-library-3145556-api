<?php

namespace App\Application\Ports;

/** Cover binaries live outside the database; the DB keeps only an opaque key (plan D-08). */
interface CoverStorage
{
    public function put(string $bytes, string $mimeType): string;

    public function delete(string $coverKey): void;
}
