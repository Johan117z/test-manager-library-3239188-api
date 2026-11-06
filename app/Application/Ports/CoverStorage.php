<?php

namespace App\Application\Ports;

/** Binary store for covers (disk locally, S3 later). The database keeps only the opaque key (plan D-08). */
interface CoverStorage
{
    /** Stores the bytes and returns a new opaque key (never a path). */
    public function put(string $bytes): string;

    public function get(string $key): ?string;

    public function delete(string $key): void;
}
