<?php

namespace App\Adapters\Storage;

use App\Application\Ports\CoverStorage;
use App\Application\Ports\IdGenerator;

/** Plan D-08: binary on disk under COVERS_DIR; the DB keeps only the opaque key (never a path). */
final class DiskCoverStorage implements CoverStorage
{
    public function __construct(private string $dir, private IdGenerator $ids)
    {
    }

    public function put(string $bytes, string $mimeType): string
    {
        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
        $key = $this->ids->next().($mimeType === 'image/png' ? '.png' : '.jpg');
        file_put_contents($this->dir.'/'.$key, $bytes);

        return $key;
    }

    public function delete(string $coverKey): void
    {
        @unlink($this->dir.'/'.basename($coverKey));
    }

    /** @return array{bytes: string, mime: string}|null */
    public function read(string $coverKey): ?array
    {
        $path = $this->dir.'/'.basename($coverKey);
        if (! is_file($path)) {
            return null;
        }

        return ['bytes' => (string) file_get_contents($path), 'mime' => str_ends_with($coverKey, '.png') ? 'image/png' : 'image/jpeg'];
    }
}
