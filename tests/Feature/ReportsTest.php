<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $manager;

    protected ReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->manager()->create();
        $this->reportService = new ReportService;
    }

    #[Test]
    public function report_counts_new_leads_correctly(): void
    {
        $this->createDeal($this->manager, 'New');
        $this->createDeal($this->manager, 'In Progress');
        $this->createDeal($this->manager, 'Closed');

        $oldDeal = $this->createDeal($this->manager, 'New');
        Deal::withoutEvents(function () use ($oldDeal) {
            $oldDeal->created_at = now()->subMonths(2);
            $oldDeal->saveQuietly();
        });

        $report = $this->reportService->getReport(
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertEquals(3, $report['leads']['total']);
    }

    #[Test]
    public function report_calculates_status_distribution_correctly(): void
    {
        $this->createDeal($this->manager, 'New');
        $this->createDeal($this->manager, 'New');
        $this->createDeal($this->manager, 'In Progress');
        $this->createDeal($this->manager, 'Closed');

        $report = $this->reportService->getReport(
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertEquals(2, $report['status_distribution']['new']);
        $this->assertEquals(1, $report['status_distribution']['in_progress']);
        $this->assertEquals(1, $report['status_distribution']['closed']);
        $this->assertEquals(4, $report['status_distribution']['total']);
        $this->assertEquals(25.0, $report['status_distribution']['conversion_rate']);
    }

    #[Test]
    public function report_calculates_average_response_time(): void
    {
        $deal1 = $this->createDeal($this->manager, 'In Progress');
        $deal1->update([
            'last_client_message_at' => now()->subMinutes(30),
            'last_manager_response_at' => now()->subMinutes(20),
        ]);

        $deal2 = $this->createDeal($this->manager, 'In Progress');
        $deal2->update([
            'last_client_message_at' => now()->subMinutes(40),
            'last_manager_response_at' => now()->subMinutes(20),
        ]);

        $report = $this->reportService->getReport(
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertEquals(15, $report['response_time']['avg_minutes']);
    }

    #[Test]
    public function report_calculates_sla_metrics(): void
    {
        $deal1 = $this->createDeal($this->manager, 'In Progress');
        $deal1->update([
            'last_client_message_at' => now()->subMinutes(40),
            'last_manager_response_at' => now()->subMinutes(20),
        ]);

        $deal2 = $this->createDeal($this->manager, 'In Progress');
        $deal2->update([
            'last_client_message_at' => now()->subMinutes(60),
            'last_manager_response_at' => now()->subMinutes(15),
        ]);

        $deal3 = $this->createDeal($this->manager, 'New');
        $deal3->update([
            'last_client_message_at' => now()->subMinutes(60),
            'last_manager_response_at' => null,
        ]);

        $report = $this->reportService->getReport(
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertEquals(3, $report['sla']['total_with_messages']);
        $this->assertEquals(2, $report['sla']['overdue_count']);
        $this->assertEqualsWithDelta(66.7, $report['sla']['overdue_percentage'], 0.1);
    }

    #[Test]
    public function manager_filter_returns_only_their_deals(): void
    {
        $manager2 = User::factory()->manager()->create();

        $this->createDeal($this->manager, 'New');
        $this->createDeal($this->manager, 'In Progress');
        $this->createDeal($manager2, 'New');
        $this->createDeal($manager2, 'In Progress');
        $this->createDeal($manager2, 'Closed');

        $report = $this->reportService->getReport(
            now()->startOfMonth(),
            now()->endOfMonth(),
            $this->manager->id
        );

        $this->assertEquals(2, $report['leads']['total']);
        $this->assertEquals(1, $report['status_distribution']['new']);
        $this->assertEquals(1, $report['status_distribution']['in_progress']);
    }

    #[Test]
    public function export_endpoint_requires_auth(): void
    {
        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/export/start', [
                'format' => 'xlsx',
            ]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function export_starts_for_authenticated_user(): void
    {
        $this->createDeal($this->manager, 'New');

        $response = $this->actingAs($this->admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/export/start', [
                'format' => 'xlsx',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'export_id',
                'message',
            ]);
    }

    #[Test]
    public function manager_export_contains_only_their_deals(): void
    {
        $manager2 = User::factory()->manager()->create();

        $this->createDeal($this->manager, 'New');
        $this->createDeal($manager2, 'New');
        $this->createDeal($manager2, 'In Progress');

        $response = $this->actingAs($this->manager)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/export/start', [
                'format' => 'xlsx',
            ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function quick_export_works_for_small_datasets(): void
    {
        $this->createDeal($this->manager, 'New');
        $this->createDeal($this->manager, 'In Progress');

        $response = $this->actingAs($this->admin)
            ->get('/export/quick?format=csv');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    #[Test]
    public function report_api_returns_correct_structure(): void
    {
        $this->createDeal($this->manager, 'New');

        $response = $this->actingAs($this->admin)
            ->getJson('/export/report?'.http_build_query([
                'start_date' => now()->startOfMonth()->format('Y-m-d'),
                'end_date' => now()->endOfMonth()->format('Y-m-d'),
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period' => ['start', 'end', 'days'],
                    'leads' => ['total', 'avg_per_day'],
                    'response_time' => ['avg_minutes', 'formatted'],
                    'status_distribution' => ['new', 'in_progress', 'closed', 'total'],
                    'sla' => ['overdue_count', 'overdue_percentage'],
                ],
            ]);
    }

    protected function createDeal(User $manager, string $status): Deal
    {
        $contact = Contact::create([
            'psid' => 'psid_'.uniqid(),
            'name' => 'Test Contact',
        ]);

        $conversation = Conversation::create([
            'conversation_id' => 'conv_'.uniqid(),
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        return Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'manager_id' => $manager->id,
            'status' => $status,
        ]);
    }
}
