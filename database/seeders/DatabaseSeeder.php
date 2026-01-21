<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Запустить сидеры базы данных.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
        ]);
    }
}
