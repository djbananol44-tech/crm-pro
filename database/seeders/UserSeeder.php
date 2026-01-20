<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Создание начальных пользователей системы.
     */
    public function run(): void
    {
        // Администратор
        User::updateOrCreate(
            ['email' => 'admin@crm.test'],
            [
                'name' => 'Администратор',
                'email' => 'admin@crm.test',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✓ Создан администратор: admin@crm.test');

        // Менеджер
        User::updateOrCreate(
            ['email' => 'manager@crm.test'],
            [
                'name' => 'Менеджер',
                'email' => 'manager@crm.test',
                'password' => Hash::make('manager123'),
                'role' => 'manager',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✓ Создан менеджер: manager@crm.test');
    }
}
