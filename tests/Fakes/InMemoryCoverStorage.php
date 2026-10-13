<?php

namespace Tests\Fakes;

use App\Application\Ports\CoverStorage;

final class InMemoryCoverStorage implements CoverStorage
{
    /** @var array<string, string> */
    public array $files = [];

    private int $n = 0;

    public function put(string $bytes, string $mimeType): string
    {
        $key = 'cover-'.++$this->n;
        $this->files[$key] = $bytes;

        return $key;
    }

    public function delete(string $coverKey): void
    {
        unset($this->files[$coverKey]);
    }
}
