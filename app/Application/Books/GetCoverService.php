<?php

namespace App\Application\Books;

use App\Application\Ports\CoverStorage;
use App\Domain\Shared\BusinessRuleViolation;

/** GET /books/{id}/cover: the binary or 404 (team decision #2: any authenticated role). */
final class GetCoverService
{
    public function __construct(
        private readonly BookCatalog $catalog,
        private readonly CoverStorage $storage,
    ) {
    }

    /** @return array{bytes: string, mime: string} */
    public function get(string $bookId): array
    {
        $key = $this->catalog->activeBook($bookId)->coverKey();
        $bytes = $key === null ? null : $this->storage->get($key);
        $mime = $bytes === null ? null : CoverImage::mimeOf($bytes);
        if ($bytes === null || $mime === null) {
            throw BusinessRuleViolation::notFound();
        }

        return ['bytes' => $bytes, 'mime' => $mime];
    }
}
