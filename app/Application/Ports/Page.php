<?php

namespace App\Application\Ports;

/**
 * @template T
 */
final class Page
{
    /** @param list<T> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $size,
        public readonly int $total,
    ) {
    }

    public const MAX_SIZE = 50;

    /** Team decision #3: size above 50 is capped, not rejected. @return array{int, int} */
    public static function normalize(?int $page, ?int $size): array
    {
        return [max(1, $page ?? 1), min(self::MAX_SIZE, max(1, $size ?? 20))];
    }
}
