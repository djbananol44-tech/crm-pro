<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Тестовые учётные записи.
     *
     * ВАЖНО: эти креды используются для:
     * - Первого входа после установки
     * - Тестов (LoginTest, AuthorizationTest)
     * - Документации (README.md)
     *
     * При изменении — обновить README.md и тесты!
     */
    public const ADMIN_EMAIL = 'admin@crm.test';

    public const ADMIN_PASSWORD = 'admin123';

    public const MANAGER_EMAIL = 'manager@crm.test';

    public const MANAGER_PASSWORD = 'manager123';

    /**
     * Создание начальных пользователей системы.
     *
     * Идемпотентно — можно запускать многократно без дублей.
     */
    public function run(): void
    {
        $this->createAdmin();
        $this->createManager();
    }

    /**
     * Создать или обновить администратора.
     */
    public function createAdmin(): User
    {
        $admin = User::updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Администратор',
                'password' => Hash::make(self::ADMIN_PASSWORD),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        if ($this->command) {
            $this->command->info('✓ Admin: '.self::ADMIN_EMAIL.' / '.self::ADMIN_PASSWORD);
        }

        return $admin;
    }

    /**
     * Создать или обновить менеджера.
     */
    public function createManager(): User
    {
        $manager = User::updateOrCreate(
            ['email' => self::MANAGER_EMAIL],
            [
                'name' => 'Менеджер',
                'password' => Hash::make(self::MANAGER_PASSWORD),
                'role' => 'manager',
                'email_verified_at' => now(),
            ]
        );

        if ($this->command) {
            $this->command->info('✓ Manager: '.self::MANAGER_EMAIL.' / '.self::MANAGER_PASSWORD);
        }

        return $manager;
    }

    /**
     * Статический метод для быстрого создания тестовых пользователей.
     *
     * Полезно для тестов и artisan tinker.
     */
    public static function ensureTestUsers(): array
    {
        $admin = User::updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Администратор',
                'password' => Hash::make(self::ADMIN_PASSWORD),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $manager = User::updateOrCreate(
            ['email' => self::MANAGER_EMAIL],
            [
                'name' => 'Менеджер',
                'password' => Hash::make(self::MANAGER_PASSWORD),
                'role' => 'manager',
                'email_verified_at' => now(),
            ]
        );

        return compact('admin', 'manager');
    }
}
