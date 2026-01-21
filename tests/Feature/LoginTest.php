<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Тесты аутентификации и доступа.
 *
 * Проверяем:
 * - Тестовые аккаунты работают после db:seed
 * - Admin имеет доступ к /admin
 * - Manager имеет доступ к /deals
 * - Повторный seeder не создаёт дублей
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function users_are_seeded_correctly(): void
    {
        $this->seed(UserSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => UserSeeder::ADMIN_EMAIL,
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => UserSeeder::MANAGER_EMAIL,
            'role' => 'manager',
        ]);
    }

    #[Test]
    public function seeder_is_idempotent_no_duplicates(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertEquals(1, User::where('email', UserSeeder::ADMIN_EMAIL)->count());
        $this->assertEquals(1, User::where('email', UserSeeder::MANAGER_EMAIL)->count());
    }

    #[Test]
    public function admin_can_login_with_seeded_credentials(): void
    {
        $this->seed(UserSeeder::class);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/login', [
                'email' => UserSeeder::ADMIN_EMAIL,
                'password' => UserSeeder::ADMIN_PASSWORD,
            ]);

        // После логина админ редиректится на /admin
        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs(User::where('email', UserSeeder::ADMIN_EMAIL)->first());
    }

    #[Test]
    public function manager_can_login_with_seeded_credentials(): void
    {
        $this->seed(UserSeeder::class);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/login', [
                'email' => UserSeeder::MANAGER_EMAIL,
                'password' => UserSeeder::MANAGER_PASSWORD,
            ]);

        // После логина менеджер редиректится на /deals
        $response->assertRedirect('/deals');
        $this->assertAuthenticatedAs(User::where('email', UserSeeder::MANAGER_EMAIL)->first());
    }

    #[Test]
    public function invalid_credentials_are_rejected(): void
    {
        $this->seed(UserSeeder::class);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/login', [
                'email' => UserSeeder::ADMIN_EMAIL,
                'password' => 'wrong_password',
            ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    #[Test]
    public function admin_can_access_admin_panel(): void
    {
        $this->seed(UserSeeder::class);

        $admin = User::where('email', UserSeeder::ADMIN_EMAIL)->first();

        $response = $this->actingAs($admin)->get('/admin');

        $this->assertTrue(
            in_array($response->status(), [200, 302]),
            "Admin should access /admin, got: {$response->status()}"
        );
    }

    #[Test]
    public function manager_can_access_deals_page(): void
    {
        $this->seed(UserSeeder::class);

        $manager = User::where('email', UserSeeder::MANAGER_EMAIL)->first();

        $response = $this->actingAs($manager)->get('/deals');

        $response->assertOk();
    }

    #[Test]
    public function manager_is_redirected_to_deals_from_root(): void
    {
        $this->seed(UserSeeder::class);

        $manager = User::where('email', UserSeeder::MANAGER_EMAIL)->first();

        $response = $this->actingAs($manager)->get('/');
        $response->assertRedirect('/deals');
    }

    #[Test]
    public function admin_is_redirected_to_admin_from_root(): void
    {
        $this->seed(UserSeeder::class);

        $admin = User::where('email', UserSeeder::ADMIN_EMAIL)->first();

        $response = $this->actingAs($admin)->get('/');
        $response->assertRedirect('/admin');
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $response = $this->get('/deals');
        $response->assertRedirect('/login');

        $response = $this->get('/admin');
        $this->assertTrue(
            in_array($response->status(), [302]),
            'Guest should be redirected from /admin'
        );
    }

    #[Test]
    public function ensure_test_users_static_method_works(): void
    {
        $users = UserSeeder::ensureTestUsers();

        $this->assertArrayHasKey('admin', $users);
        $this->assertArrayHasKey('manager', $users);

        $this->assertEquals('admin', $users['admin']->role);
        $this->assertEquals('manager', $users['manager']->role);

        $users2 = UserSeeder::ensureTestUsers();

        $this->assertEquals($users['admin']->id, $users2['admin']->id);
        $this->assertEquals($users['manager']->id, $users2['manager']->id);
    }

    #[Test]
    public function login_page_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertOk();
    }

    #[Test]
    public function logout_works(): void
    {
        $this->seed(UserSeeder::class);

        $admin = User::where('email', UserSeeder::ADMIN_EMAIL)->first();

        $response = $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/logout');

        // После logout редирект на /login
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    #[Test]
    public function admin_password_is_hashed_correctly(): void
    {
        $this->seed(UserSeeder::class);

        $admin = User::where('email', UserSeeder::ADMIN_EMAIL)->first();

        $this->assertTrue(Hash::check(UserSeeder::ADMIN_PASSWORD, $admin->password));
    }

    #[Test]
    public function manager_password_is_hashed_correctly(): void
    {
        $this->seed(UserSeeder::class);

        $manager = User::where('email', UserSeeder::MANAGER_EMAIL)->first();

        $this->assertTrue(Hash::check(UserSeeder::MANAGER_PASSWORD, $manager->password));
    }
}
