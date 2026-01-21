<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * JGGL CRM — Search Feature Tests
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * Тесты полнотекстового поиска по deals.
 *
 * @see docs/search.md
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->create([
            'role' => 'manager',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // A) ПОЛНОТЕКСТОВЫЙ ПОИСК
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function search_finds_deal_by_contact_name(): void
    {
        $contact = Contact::create([
            'psid' => 'psid_'.uniqid(),
            'name' => 'Иван Петров',
        ]);

        $conversation = Conversation::create([
            'conversation_id' => 'conv_'.uniqid(),
            'contact_id' => $contact->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        $deal = Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'status' => 'New',
        ]);

        $this->reindexDeal($deal);

        $response = $this->actingAs($this->admin)
            ->get('/deals?search=Петров');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard')
            ->has('deals.data', 1)
            ->where('deals.data.0.id', $deal->id)
        );
    }

    #[Test]
    public function search_finds_deal_by_ai_summary(): void
    {
        $contact = Contact::create([
            'psid' => 'psid_ai_'.uniqid(),
            'name' => 'Клиент Тест',
        ]);

        $conversation = Conversation::create([
            'conversation_id' => 'conv_ai_'.uniqid(),
            'contact_id' => $contact->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        $deal = Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'status' => 'In Progress',
            'ai_summary' => 'Клиент интересуется ценой на iPhone 15 Pro Max',
        ]);

        $this->reindexDeal($deal);

        $response = $this->actingAs($this->admin)
            ->get('/deals?search=iPhone');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard')
            ->has('deals.data', 1)
            ->where('deals.data.0.id', $deal->id)
        );
    }

    #[Test]
    public function search_finds_deal_by_comment(): void
    {
        $contact = Contact::create([
            'psid' => 'psid_comment_'.uniqid(),
            'name' => 'Другой Клиент',
        ]);

        $conversation = Conversation::create([
            'conversation_id' => 'conv_comment_'.uniqid(),
            'contact_id' => $contact->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        $deal = Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'status' => 'New',
            'comment' => 'Важный клиент, перезвонить завтра утром',
        ]);

        $this->reindexDeal($deal);

        $response = $this->actingAs($this->admin)
            ->get('/deals?search=перезвонить');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard')
            ->has('deals.data', 1)
        );
    }

    #[Test]
    public function search_finds_deal_by_last_message_text(): void
    {
        $contact = Contact::create([
            'psid' => 'psid_msg_'.uniqid(),
            'name' => 'Клиент Сообщение',
        ]);

        $conversation = Conversation::create([
            'conversation_id' => 'conv_msg_'.uniqid(),
            'contact_id' => $contact->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        $deal = Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'status' => 'New',
            'last_message_text' => 'Здравствуйте, хочу узнать о доставке',
        ]);

        $this->reindexDeal($deal);

        $response = $this->actingAs($this->admin)
            ->get('/deals?search=доставке');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard')
            ->has('deals.data', 1)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // B) ТОЧНЫЙ ПОИСК (ID/PSID)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function exact_search_finds_deal_by_psid(): void
    {
        $uniquePsid = '123456789012345678';

        $contact = Contact::create([
            'psid' => $uniquePsid,
            'name' => 'Клиент PSID',
        ]);

        $conversation = Conversation::create([
            'conversation_id' => 'conv_psid_'.uniqid(),
            'contact_id' => $contact->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        $deal = Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'status' => 'New',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/deals?search='.$uniquePsid);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard')
            ->has('deals.data', 1)
            ->where('deals.data.0.contact.psid', $uniquePsid)
        );
    }

    #[Test]
    public function exact_search_finds_deal_by_id(): void
    {
        $contact = Contact::create([
            'psid' => 'psid_id_'.uniqid(),
            'name' => 'Клиент ID',
        ]);

        $conversation = Conversation::create([
            'conversation_id' => 'conv_id_'.uniqid(),
            'contact_id' => $contact->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        $deal = Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'status' => 'New',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/deals?search='.$deal->id);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard')
            ->has('deals.data', 1)
            ->where('deals.data.0.id', $deal->id)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // C) ФИЛЬТР "МОИ СДЕЛКИ" (MANAGER)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function manager_sees_only_own_deals(): void
    {
        $contact1 = Contact::create(['psid' => 'psid_m1_'.uniqid(), 'name' => 'Мой Клиент']);
        $conversation1 = Conversation::create([
            'conversation_id' => 'conv_m1_'.uniqid(),
            'contact_id' => $contact1->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);
        $myDeal = Deal::create([
            'contact_id' => $contact1->id,
            'conversation_id' => $conversation1->id,
            'status' => 'New',
            'manager_id' => $this->manager->id,
        ]);

        $contact2 = Contact::create(['psid' => 'psid_m2_'.uniqid(), 'name' => 'Чужой Клиент']);
        $conversation2 = Conversation::create([
            'conversation_id' => 'conv_m2_'.uniqid(),
            'contact_id' => $contact2->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);
        $otherDeal = Deal::create([
            'contact_id' => $contact2->id,
            'conversation_id' => $conversation2->id,
            'status' => 'New',
            'manager_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->get('/deals');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard')
            ->has('deals.data', 1)
            ->where('deals.data.0.id', $myDeal->id)
        );
    }

    #[Test]
    public function admin_sees_all_deals(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $contact = Contact::create(['psid' => 'psid_admin_'.$i.'_'.uniqid(), 'name' => "Клиент $i"]);
            $conversation = Conversation::create([
                'conversation_id' => 'conv_admin_'.$i.'_'.uniqid(),
                'contact_id' => $contact->id,
                'platform' => 'messenger',
                'updated_time' => now(),
            ]);
            Deal::create([
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
                'status' => 'New',
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->get('/deals');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard')
            ->has('deals.data', 3)
        );
    }

    #[Test]
    public function filter_unassigned_shows_deals_without_manager(): void
    {
        $contact1 = Contact::create(['psid' => 'psid_ua1_'.uniqid(), 'name' => 'Без менеджера']);
        $conversation1 = Conversation::create([
            'conversation_id' => 'conv_ua1_'.uniqid(),
            'contact_id' => $contact1->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);
        $unassigned = Deal::create([
            'contact_id' => $contact1->id,
            'conversation_id' => $conversation1->id,
            'status' => 'New',
            'manager_id' => null,
        ]);

        $contact2 = Contact::create(['psid' => 'psid_ua2_'.uniqid(), 'name' => 'С менеджером']);
        $conversation2 = Conversation::create([
            'conversation_id' => 'conv_ua2_'.uniqid(),
            'contact_id' => $contact2->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);
        Deal::create([
            'contact_id' => $contact2->id,
            'conversation_id' => $conversation2->id,
            'status' => 'New',
            'manager_id' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/deals?unassigned=1');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard')
            ->has('deals.data', 1)
            ->where('deals.data.0.id', $unassigned->id)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // D) ПАГИНАЦИЯ И ЛИМИТЫ
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function search_results_are_paginated(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $contact = Contact::create(['psid' => 'psid_page_'.$i.'_'.uniqid(), 'name' => "Клиент Страница $i"]);
            $conversation = Conversation::create([
                'conversation_id' => 'conv_page_'.$i.'_'.uniqid(),
                'contact_id' => $contact->id,
                'platform' => 'messenger',
                'updated_time' => now(),
            ]);
            Deal::create([
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
                'status' => 'New',
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->get('/deals');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard')
            ->has('deals.data', 15)
            ->where('deals.last_page', 2)
            ->where('deals.total', 20)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // E) СОРТИРОВКА
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function sort_by_ai_score_works(): void
    {
        $contact1 = Contact::create(['psid' => 'psid_score1_'.uniqid(), 'name' => 'Низкий Score']);
        $conversation1 = Conversation::create([
            'conversation_id' => 'conv_score1_'.uniqid(),
            'contact_id' => $contact1->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);
        $lowScore = Deal::create([
            'contact_id' => $contact1->id,
            'conversation_id' => $conversation1->id,
            'status' => 'New',
            'ai_score' => 30,
        ]);

        $contact2 = Contact::create(['psid' => 'psid_score2_'.uniqid(), 'name' => 'Высокий Score']);
        $conversation2 = Conversation::create([
            'conversation_id' => 'conv_score2_'.uniqid(),
            'contact_id' => $contact2->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);
        $highScore = Deal::create([
            'contact_id' => $contact2->id,
            'conversation_id' => $conversation2->id,
            'status' => 'New',
            'ai_score' => 95,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/deals?sort=score');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard')
            ->has('deals.data', 2)
            ->where('deals.data.0.id', $highScore->id)
            ->where('deals.data.1.id', $lowScore->id)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    protected function reindexDeal(Deal $deal): void
    {
        try {
            DB::statement('
                UPDATE deals SET updated_at = NOW() WHERE id = ?
            ', [$deal->id]);
        } catch (\Exception $e) {
            // В тестах с SQLite триггер не сработает — это ОК
        }
    }
}
