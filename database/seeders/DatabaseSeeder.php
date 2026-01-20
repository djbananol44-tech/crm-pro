<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
