<?php

namespace App\Infrastructure\Persistence;

use App\Application\Ports\CategoryRepository;
use App\Domain\Book\Category;
use Illuminate\Support\Facades\DB;

final class DbCategoryRepository implements CategoryRepository
{
    /** @var array<string, Category>|null five seeded rows, read once per request */
    private ?array $cache = null;

    public function all(): array
    {
        return array_values($this->load());
    }

    public function find(string $id): ?Category
    {
        return $this->load()[$id] ?? null;
    }

    /** @return array<string, Category> */
    private function load(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach (DB::table('library.category')->orderBy('name')->get() as $row) {
                $this->cache[$row->id] = new Category($row->id, $row->name);
            }
        }

        return $this->cache;
    }
}
