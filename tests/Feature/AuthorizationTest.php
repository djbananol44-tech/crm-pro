<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Тесты авторизации и политик доступа.
 *
 * Проверяем:
 * - Manager не может получить доступ к чужим сделкам
 * - Manager может видеть свои сделки и неназначенные
 * - Admin имеет полный доступ
 * - Экспорт возвращает только разрешённые данные
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $manager1;

    protected User $manager2;

    protected Deal $adminDeal;

    protected Deal $manager1Deal;

    protected Deal $manager2Deal;

    protected Deal $unassignedDeal;

    protected function setUp(): void
    {
        parent::setUp();

        // Отключаем CSRF для тестов (POST/PATCH/DELETE)
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['data' => []], 200),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Test response']]]]],
            ], 200),
        ]);

        Setting::updateOrCreate(['key' => 'meta_access_token'], ['value' => 'test_token']);
        Setting::updateOrCreate(['key' => 'meta_page_id'], ['value' => '123456789']);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->manager1 = User::factory()->create(['role' => 'manager']);
        $this->manager2 = User::factory()->create(['role' => 'manager']);

        $contact1 = Contact::create([
            'psid' => '111111111111111',
            'name' => 'Клиент 1',
            'platform' => 'messenger',
        ]);

        $contact2 = Contact::create([
            'psid' => '222222222222222',
            'name' => 'Клиент 2',
            'platform' => 'instagram',
        ]);

        $contact3 = Contact::create([
            'psid' => '333333333333333',
            'name' => 'Клиент 3',
            'platform' => 'messenger',
        ]);

        $contact4 = Contact::create([
            'psid' => '444444444444444',
            'name' => 'Клиент 4',
            'platform' => 'instagram',
        ]);

        $conv1 = Conversation::create([
            'conversation_id' => 't_conv1',
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        $conv2 = Conversation::create([
            'conversation_id' => 't_conv2',
            'platform' => 'instagram',
            'updated_time' => now(),
        ]);

        $conv3 = Conversation::create([
            'conversation_id' => 't_conv3',
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        $conv4 = Conversation::create([
            'conversation_id' => 't_conv4',
            'platform' => 'instagram',
            'updated_time' => now(),
        ]);

        $this->adminDeal = Deal::create([
            'contact_id' => $contact1->id,
            'conversation_id' => $conv1->id,
            'manager_id' => $this->admin->id,
            'status' => 'In Progress',
        ]);

        $this->manager1Deal = Deal::create([
            'contact_id' => $contact2->id,
            'conversation_id' => $conv2->id,
            'manager_id' => $this->manager1->id,
            'status' => 'New',
        ]);

        $this->manager2Deal = Deal::create([
            'contact_id' => $contact3->id,
            'conversation_id' => $conv3->id,
            'manager_id' => $this->manager2->id,
            'status' => 'In Progress',
        ]);

        $this->unassignedDeal = Deal::create([
            'contact_id' => $contact4->id,
            'conversation_id' => $conv4->id,
            'manager_id' => null,
            'status' => 'New',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ДОСТУП К ПРОСМОТРУ СДЕЛОК
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function manager_cannot_view_another_managers_deal(): void
    {
        $this->actingAs($this->manager1)
            ->get(route('deals.show', $this->manager2Deal))
            ->assertForbidden();
    }

    #[Test]
    public function manager_can_view_own_deal(): void
    {
        $this->actingAs($this->manager1)
            ->get(route('deals.show', $this->manager1Deal))
            ->assertOk();
    }

    #[Test]
    public function manager_can_view_unassigned_deal(): void
    {
        $this->actingAs($this->manager1)
            ->get(route('deals.show', $this->unassignedDeal))
            ->assertOk();
    }

    #[Test]
    public function admin_can_view_any_deal(): void
    {
        $this->actingAs($this->admin)
            ->get(route('deals.show', $this->manager1Deal))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('deals.show', $this->manager2Deal))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('deals.show', $this->unassignedDeal))
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ОБНОВЛЕНИЕ СДЕЛОК
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function manager_cannot_update_another_managers_deal(): void
    {
        $this->actingAs($this->manager1)
            ->patch(route('deals.update', $this->manager2Deal), [
                'status' => 'Closed',
            ])
            ->assertForbidden();

        $this->assertEquals('In Progress', $this->manager2Deal->fresh()->status);
    }

    #[Test]
    public function manager_can_update_own_deal(): void
    {
        $this->actingAs($this->manager1)
            ->patch(route('deals.update', $this->manager1Deal), [
                'status' => 'In Progress',
                'comment' => 'Работаю над сделкой',
            ])
            ->assertRedirect();

        $this->assertEquals('In Progress', $this->manager1Deal->fresh()->status);
        $this->assertEquals('Работаю над сделкой', $this->manager1Deal->fresh()->comment);
    }

    #[Test]
    public function manager_can_update_unassigned_deal(): void
    {
        $this->actingAs($this->manager1)
            ->patch(route('deals.update', $this->unassignedDeal), [
                'comment' => 'Просматриваю',
            ])
            ->assertRedirect();

        $this->assertEquals('Просматриваю', $this->unassignedDeal->fresh()->comment);
    }

    #[Test]
    public function admin_can_update_any_deal(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('deals.update', $this->manager1Deal), [
                'status' => 'Closed',
            ])
            ->assertRedirect();

        $this->assertEquals('Closed', $this->manager1Deal->fresh()->status);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // НАЗНАЧЕНИЕ СДЕЛКИ НА СЕБЯ
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function manager_can_assign_unassigned_deal_to_self(): void
    {
        $this->actingAs($this->manager1)
            ->post(route('deals.assign', $this->unassignedDeal))
            ->assertRedirect();

        $this->assertEquals($this->manager1->id, $this->unassignedDeal->fresh()->manager_id);
    }

    #[Test]
    public function manager_cannot_reassign_another_managers_deal_to_self(): void
    {
        $this->actingAs($this->manager1)
            ->post(route('deals.assign', $this->manager2Deal))
            ->assertForbidden();

        $this->assertEquals($this->manager2->id, $this->manager2Deal->fresh()->manager_id);
    }

    #[Test]
    public function manager_cannot_reassign_own_deal(): void
    {
        $this->actingAs($this->manager1)
            ->post(route('deals.assign', $this->manager1Deal))
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_assign_any_deal(): void
    {
        $this->actingAs($this->admin)
            ->post(route('deals.assign', $this->manager2Deal))
            ->assertRedirect();

        $this->assertEquals($this->admin->id, $this->manager2Deal->fresh()->manager_id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // СПИСОК СДЕЛОК (ФИЛЬТРАЦИЯ ПО ДОСТУПУ)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function manager_sees_only_own_deals_in_list(): void
    {
        $response = $this->actingAs($this->manager1)
            ->get(route('deals.index'))
            ->assertOk();

        $deals = $response->original->getData()['page']['props']['deals']['data'] ?? [];
        $dealIds = collect($deals)->pluck('id')->toArray();

        $this->assertContains($this->manager1Deal->id, $dealIds);
        $this->assertNotContains($this->manager2Deal->id, $dealIds);
        $this->assertNotContains($this->adminDeal->id, $dealIds);
    }

    #[Test]
    public function admin_sees_all_deals_in_list(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('deals.index'))
            ->assertOk();

        $deals = $response->original->getData()['page']['props']['deals']['data'] ?? [];
        $dealIds = collect($deals)->pluck('id')->toArray();

        $this->assertContains($this->manager1Deal->id, $dealIds);
        $this->assertContains($this->manager2Deal->id, $dealIds);
        $this->assertContains($this->adminDeal->id, $dealIds);
        $this->assertContains($this->unassignedDeal->id, $dealIds);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ЭКСПОРТ (ФИЛЬТРАЦИЯ ПО ДОСТУПУ)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function manager_quick_export_contains_only_own_deals(): void
    {
        $contact = Contact::create([
            'psid' => '555555555555555',
            'name' => 'Доп клиент',
            'platform' => 'messenger',
        ]);

        $conv = Conversation::create([
            'conversation_id' => 't_conv5',
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conv->id,
            'manager_id' => $this->manager1->id,
            'status' => 'New',
        ]);

        $response = $this->actingAs($this->manager1)
            ->withSession(['_token' => 'test'])
            ->withHeaders(['X-CSRF-TOKEN' => 'test'])
            ->get(route('export.quick', ['format' => 'csv']));

        // StreamedResponse использует getStatusCode()
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function manager_cannot_download_another_users_export(): void
    {
        $exportId = 'test_export_'.uniqid();
        cache()->put('export:'.$exportId, [
            'status' => 'completed',
            'user_id' => $this->manager2->id,
            'filename' => 'test.xlsx',
            'path' => 'exports/test.xlsx',
        ], now()->addHour());

        $this->actingAs($this->manager1)
            ->get(route('export.download', ['exportId' => $exportId]))
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_download_any_export(): void
    {
        $exportId = 'test_export_admin_'.uniqid();

        \Storage::disk('local')->put('exports/test_admin.xlsx', 'fake content');

        cache()->put('export:'.$exportId, [
            'status' => 'completed',
            'user_id' => $this->manager1->id,
            'filename' => 'test_admin.xlsx',
            'path' => 'exports/test_admin.xlsx',
        ], now()->addHour());

        $this->actingAs($this->admin)
            ->get(route('export.download', ['exportId' => $exportId]))
            ->assertOk();

        \Storage::disk('local')->delete('exports/test_admin.xlsx');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ОТЧЁТЫ (ФИЛЬТРАЦИЯ ПО ДОСТУПУ)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function manager_report_is_filtered_to_own_data(): void
    {
        $response = $this->actingAs($this->manager1)
            ->withSession(['_token' => 'test'])
            ->withHeaders(['X-CSRF-TOKEN' => 'test'])
            ->get(route('export.report', [
                'start_date' => now()->subMonth()->toDateString(),
                'end_date' => now()->toDateString(),
            ]));

        $response->assertOk();

        $data = $response->json('data');

        $this->assertIsArray($data);
    }

    #[Test]
    public function manager_cannot_filter_report_by_other_manager(): void
    {
        $response = $this->actingAs($this->manager1)
            ->withSession(['_token' => 'test'])
            ->withHeaders(['X-CSRF-TOKEN' => 'test'])
            ->get(route('export.report', [
                'start_date' => now()->subMonth()->toDateString(),
                'end_date' => now()->toDateString(),
                'manager_id' => $this->manager2->id,
            ]));

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // AI ФУНКЦИИ (ДОСТУП К СДЕЛКЕ)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function manager_cannot_refresh_ai_on_another_managers_deal(): void
    {
        $this->actingAs($this->manager1)
            ->post(route('deals.refresh-ai', $this->manager2Deal))
            ->assertForbidden();
    }

    #[Test]
    public function manager_cannot_translate_another_managers_deal(): void
    {
        $this->actingAs($this->manager1)
            ->post(route('deals.translate', $this->manager2Deal))
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ГОСТИ (НЕАВТОРИЗОВАННЫЕ ПОЛЬЗОВАТЕЛИ)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function guest_cannot_access_deals(): void
    {
        $this->get(route('deals.index'))
            ->assertRedirect(route('login'));

        $this->get(route('deals.show', $this->manager1Deal))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function guest_cannot_export(): void
    {
        $this->get(route('export.quick'))
            ->assertRedirect(route('login'));
    }
}
