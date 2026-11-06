<?php

namespace App\Application\Ports;

use App\Domain\Book\Category;

interface CategoryRepository
{
    /** @return list<Category> ordered by name */
    public function all(): array;

    public function find(string $id): ?Category;
}
