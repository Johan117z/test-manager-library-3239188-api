<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Intentionally empty: the five categories are seeded by the initial migration
     * (data-model.md §9), the admin is created at startup from the environment,
     * and demo data is loaded through the API by the tool repo.
     */
    public function run(): void
    {
    }
}
