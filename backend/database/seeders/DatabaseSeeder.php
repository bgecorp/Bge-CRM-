<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sample records only, so the screens have something to show.
        // Skip by running `php artisan migrate` without `--seed`.
        $this->call(SampleDataSeeder::class);
    }
}
