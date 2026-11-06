<?php

namespace App\Domain\Book;

/** One of the five fixed, seeded categories (read only). */
final class Category
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {
    }
}
