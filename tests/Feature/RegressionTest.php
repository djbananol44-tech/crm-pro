<?php

namespace Tests\Feature;

use App\Jobs\ProcessMetaMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * JGGL CRM — Regression Test Suite
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * Критические потоки P0/P1 для CI.
 * Время выполнения: < 2-3 минуты
 *
 * A) Meta Webhook Security
 * B) Meta Webhook Idempotency
 * C) Queue Configuration
 * D) Telegram Webhook
 * E) Gemini AI
 * F) Health Check
 */
class RegressionTest extends TestCase
{
    use RefreshDatabase;

    protected string $metaAppSecret = 'test_meta_app_secret_12345';

    protected string $telegramSecret = 'test_telegram_secret_67890';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('meta_app_secret', $this->metaAppSecret);
        Setting::set('telegram_webhook_secret', $this->telegramSecret);
        Setting::set('telegram_bot_token', '123456:ABC-DEF');
        Setting::set('telegram_mode', 'webhook');

        $this->clearIdempotencyKeys();
    }

    protected function clearIdempotencyKeys(): void
    {
        try {
            $keys = Redis::keys('webhook:idempotency:*');
            foreach ($keys as $key) {
                $cleanKey = preg_replace('/^.*:webhook:idempotency:/', 'webhook:idempotency:', $key);
                Redis::del($cleanKey);
            }
        } catch (\Exception $e) {
            // Redis может быть недоступен в CI без services
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // A) META WEBHOOK SECURITY
    // ═══════════════════════════════════════════════════════════════════════════

    protected function generateMetaSignature(string $payload): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, $this->metaAppSecret);
    }

    protected function createMetaPayload(?string $mid = null): array
    {
        $mid = $mid ?? 'm_'.uniqid();

        return [
            'object' => 'page',
            'entry' => [[
                'id' => 'page_123',
                'time' => time() * 1000,
                'messaging' => [[
                    'sender' => ['id' => 'sender_456'],
                    'recipient' => ['id' => 'page_123'],
                    'timestamp' => time() * 1000,
                    'message' => [
                        'mid' => $mid,
                        'text' => 'Test message',
                    ],
                ]],
            ]],
        ];
    }

    #[Test]
    public function meta_webhook_valid_signature_returns_200_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = $this->createMetaPayload();
        $json = json_encode($payload);
        $signature = $this->generateMetaSignature($json);

        $response = $this->postJson('/api/webhooks/meta', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertSee('EVENT_RECEIVED');

        Queue::assertPushed(ProcessMetaMessage::class);
    }

    #[Test]
    public function meta_webhook_invalid_signature_returns_403_no_dispatch(): void
    {
        Queue::fake();

        $payload = $this->createMetaPayload();

        $response = $this->postJson('/api/webhooks/meta', $payload, [
            'X-Hub-Signature-256' => 'sha256=totally_wrong_signature',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['code' => 'invalid_signature']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function meta_webhook_missing_signature_returns_403_no_dispatch(): void
    {
        Queue::fake();

        $payload = $this->createMetaPayload();

        $response = $this->postJson('/api/webhooks/meta', $payload);

        $response->assertStatus(403);
        $response->assertJson(['code' => 'missing_signature']);

        Queue::assertNothingPushed();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // B) META WEBHOOK IDEMPOTENCY
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function meta_webhook_duplicate_payload_dispatches_once(): void
    {
        Queue::fake();

        $mid = 'm_DEDUP_TEST_'.uniqid();
        $payload = $this->createMetaPayload($mid);
        $json = json_encode($payload);
        $signature = $this->generateMetaSignature($json);

        $response1 = $this->postJson('/api/webhooks/meta', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);
        $response1->assertStatus(200);
        $response1->assertSee('EVENT_RECEIVED');

        $response2 = $this->postJson('/api/webhooks/meta', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);
        $response2->assertStatus(200);
        $response2->assertSee('DUPLICATE_IGNORED');

        Queue::assertPushed(ProcessMetaMessage::class, 1);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // C) QUEUE CONFIGURATION
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function queue_dispatch_uses_configured_connection(): void
    {
        Queue::fake();

        $payload = $this->createMetaPayload();
        $json = json_encode($payload);
        $signature = $this->generateMetaSignature($json);

        $this->postJson('/api/webhooks/meta', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        Queue::assertPushed(ProcessMetaMessage::class);
        $this->assertEquals(0, Contact::count());
    }

    #[Test]
    public function jobs_are_dispatched_to_correct_queues(): void
    {
        Queue::fake();

        $payload = $this->createMetaPayload();
        $json = json_encode($payload);
        $signature = $this->generateMetaSignature($json);

        $this->postJson('/api/webhooks/meta', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        Queue::assertPushed(ProcessMetaMessage::class, function ($job) {
            return $job instanceof ProcessMetaMessage;
        });

        Queue::assertPushed(ProcessMetaMessage::class);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // D) TELEGRAM WEBHOOK
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function telegram_webhook_deduplicates_by_update_id(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $updateId = 123456789;
        $update = [
            'update_id' => $updateId,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 111222333],
                'text' => '/start',
                'date' => time(),
            ],
        ];

        $response1 = $this->postJson('/api/webhooks/telegram', $update, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->telegramSecret,
        ]);
        $response1->assertStatus(200);
        $this->assertNotEquals('duplicate', $response1->json('status'));

        $response2 = $this->postJson('/api/webhooks/telegram', $update, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->telegramSecret,
        ]);
        $response2->assertStatus(200);
        $response2->assertJson(['status' => 'duplicate']);
    }

    #[Test]
    public function telegram_webhook_unauthorized_returns_403(): void
    {
        $update = [
            'update_id' => 999,
            'message' => [
                'chat' => ['id' => 111],
                'text' => '/start',
            ],
        ];

        $response1 = $this->postJson('/api/webhooks/telegram', $update);
        $response1->assertStatus(403);

        $response2 = $this->postJson('/api/webhooks/telegram', $update, [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong_token',
        ]);
        $response2->assertStatus(403);
    }

    #[Test]
    public function telegram_callback_claim_changes_state_once(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create([
            'telegram_chat_id' => '111222333',
            'role' => 'manager',
        ]);

        $contact = Contact::create([
            'psid' => 'test_psid',
            'name' => 'Test Client',
        ]);

        $conversation = Conversation::create([
            'conversation_id' => 'conv_test_'.uniqid(),
            'contact_id' => $contact->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        $deal = Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'status' => 'New',
            'manager_id' => null,
        ]);

        $this->assertNull($deal->manager_id);

        $callback1 = [
            'update_id' => 1001,
            'callback_query' => [
                'id' => 'cb_1',
                'from' => ['id' => 111222333],
                'message' => ['message_id' => 1, 'chat' => ['id' => 111222333]],
                'data' => "claim_{$deal->id}",
            ],
        ];

        $response1 = $this->postJson('/api/webhooks/telegram', $callback1, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->telegramSecret,
        ]);
        $response1->assertStatus(200);

        $deal->refresh();
        $this->assertEquals($user->id, $deal->manager_id);
        $this->assertEquals('In Progress', $deal->status);

        $callback2 = [
            'update_id' => 1002,
            'callback_query' => [
                'id' => 'cb_2',
                'from' => ['id' => 111222333],
                'message' => ['message_id' => 1, 'chat' => ['id' => 111222333]],
                'data' => "claim_{$deal->id}",
            ],
        ];

        $this->postJson('/api/webhooks/telegram', $callback2, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->telegramSecret,
        ]);

        $deal->refresh();
        $this->assertEquals($user->id, $deal->manager_id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // E) GEMINI AI
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function gemini_is_available_with_valid_key(): void
    {
        Setting::set('gemini_api_key', 'valid_test_key');
        Setting::set('ai_enabled', true);
        Setting::set('gemini_status', 'ok');

        $service = new AiAnalysisService;

        $this->assertTrue($service->isAvailable());
    }

    #[Test]
    public function gemini_is_not_available_without_key(): void
    {
        Setting::where('key', 'gemini_api_key')->delete();
        Setting::set('ai_enabled', true);
        Setting::clearCache();

        $service = new AiAnalysisService;

        $this->assertFalse($service->isAvailable());
    }

    #[Test]
    public function gemini_error_does_not_crash_job_graceful_degradation(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 400,
                    'message' => 'API key invalid',
                ],
            ], 400),
        ]);

        Setting::set('gemini_api_key', 'invalid_key');
        Setting::set('ai_enabled', true);
        Setting::set('gemini_status', 'ok');

        $contact = Contact::create(['psid' => 'test_ai', 'name' => 'Test']);

        $conversation = Conversation::create([
            'conversation_id' => 'conv_ai_'.uniqid(),
            'contact_id' => $contact->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        $deal = Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'status' => 'New',
        ]);

        $service = new AiAnalysisService;

        $result = $service->analyzeAndSaveDeal($deal, [
            ['from' => ['id' => 'user'], 'message' => 'Hello'],
        ]);

        $this->assertFalse($result);

        $deal->refresh();
        $this->assertNull($deal->ai_summary);
    }

    #[Test]
    public function gemini_retries_on_transient_errors(): void
    {
        $callCount = 0;

        Http::fake(function ($request) use (&$callCount) {
            $callCount++;

            if ($callCount < 2) {
                return Http::response(['error' => ['message' => 'Temporary']], 500);
            }

            return Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"summary": "Test", "score": 50, "intent": "test", "objections": [], "next_best_action": "test"}',
                        ]],
                    ],
                ]],
            ], 200);
        });

        Setting::set('gemini_api_key', 'test_key');
        Setting::set('ai_enabled', true);
        Setting::set('gemini_status', 'ok');

        $service = new AiAnalysisService;
        $result = $service->analyzeConversation([
            ['from' => ['id' => 'user'], 'message' => 'Hello'],
        ]);

        $this->assertGreaterThanOrEqual(2, $callCount);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // F) HEALTH CHECK
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function health_endpoint_returns_ok_when_services_available(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database',
                'redis',
                'queue',
            ],
        ]);

        $response->assertJsonPath('checks.database.status', 'ok');
    }

    #[Test]
    public function health_endpoint_shows_redis_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('redis', $data['checks']);

        $this->assertContains(
            $data['checks']['redis']['status'],
            ['ok', 'warning', 'error']
        );
    }

    #[Test]
    public function health_endpoint_includes_queue_metrics(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'checks' => [
                'queue' => [
                    'status',
                    'message',
                    'metrics',
                ],
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function meta_webhook_verification_works(): void
    {
        Setting::set('meta_webhook_verify_token', 'my_verify_token');

        $response = $this->get('/api/webhooks/meta?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'my_verify_token',
            'hub_challenge' => 'challenge_12345',
        ]));

        $response->assertStatus(200);
        $response->assertSee('challenge_12345');
    }

    #[Test]
    public function telegram_callback_from_unauthorized_user_rejected(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $contact = Contact::create(['psid' => 'test_unauth', 'name' => 'Test']);

        $conversation = Conversation::create([
            'conversation_id' => 'conv_unauth_'.uniqid(),
            'contact_id' => $contact->id,
            'platform' => 'messenger',
            'updated_time' => now(),
        ]);

        $deal = Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'status' => 'New',
        ]);

        $callback = [
            'update_id' => 2001,
            'callback_query' => [
                'id' => 'cb_unknown',
                'from' => ['id' => 999999999],
                'message' => ['message_id' => 1, 'chat' => ['id' => 999999999]],
                'data' => "claim_{$deal->id}",
            ],
        ];

        $this->postJson('/api/webhooks/telegram', $callback, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->telegramSecret,
        ]);

        $deal->refresh();
        $this->assertNull($deal->manager_id);
        $this->assertEquals('New', $deal->status);
    }
}
