<?php

namespace Tests\Fakes;

use App\Application\Ports\CategoryRepository;
use App\Domain\Book\Category;

final class InMemoryCategoryRepository implements CategoryRepository
{
    public const NOVELA = '11111111-1111-4111-8111-111111111111';
    public const CIENCIA = '22222222-2222-4222-8222-222222222222';

    public function all(): array
    {
        return [new Category(self::CIENCIA, 'Ciencia'), new Category(self::NOVELA, 'Novela')];
    }

    public function find(string $id): ?Category
    {
        foreach ($this->all() as $c) {
            if ($c->id === $id) {
                return $c;
            }
        }

        return null;
    }
}
