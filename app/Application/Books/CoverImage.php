<?php

namespace App\Application\Books;

/** Detects the cover type by content (magic bytes), never by extension (api-contract.md §4). */
final class CoverImage
{
    public static function mimeOf(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'image/png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            default => null,
        };
    }
}
