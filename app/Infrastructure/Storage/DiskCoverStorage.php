<?php

namespace App\Infrastructure\Storage;

use App\Application\Ports\CoverStorage;
use RuntimeException;

/** Local disk adapter. Keys are 32 random hex chars; anything else is rejected, so a key can never be a path. */
final class DiskCoverStorage implements CoverStorage
{
    public function __construct(private readonly string $directory)
    {
    }

    public function put(string $bytes): string
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Cover directory cannot be created.');
        }
        $key = bin2hex(random_bytes(16));
        if (file_put_contents($this->path($key), $bytes) === false) {
            throw new RuntimeException('Cover cannot be written.');
        }

        return $key;
    }

    public function get(string $key): ?string
    {
        if (! self::validKey($key) || ! is_file($this->path($key))) {
            return null;
        }
        $bytes = file_get_contents($this->path($key));

        return $bytes === false ? null : $bytes;
    }

    public function delete(string $key): void
    {
        if (self::validKey($key) && is_file($this->path($key))) {
            unlink($this->path($key));
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/\\').DIRECTORY_SEPARATOR.$key;
    }

    private static function validKey(string $key): bool
    {
        return (bool) preg_match('/^[0-9a-f]{32}$/', $key);
    }
}
