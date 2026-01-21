<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WebhookLog;
use App\Services\WebhookIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Тесты идемпотентности обработки webhook.
 */
class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected WebhookIdempotencyService $idempotency;

    protected string $appSecret = 'test_app_secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->idempotency = new WebhookIdempotencyService;

        // Настраиваем app secret
        Setting::set('meta_app_secret', $this->appSecret);

        // Очищаем Redis ключи идемпотентности
        try {
            $keys = Redis::keys('webhook:idempotency:*');
            foreach ($keys as $key) {
                Redis::del($key);
            }
        } catch (\Exception $e) {
            // Redis может быть недоступен
        }
    }

    /**
     * Генерация подписи для payload.
     */
    protected function generateSignature(string $payload): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, $this->appSecret);
    }

    /**
     * Создание тестового payload с message.mid.
     */
    protected function createPayload(string $mid, string $senderId = '123456', string $text = 'Hello'): array
    {
        return [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'page_123',
                    'time' => time() * 1000,
                    'messaging' => [
                        [
                            'sender' => ['id' => $senderId],
                            'recipient' => ['id' => 'page_123'],
                            'timestamp' => time() * 1000,
                            'message' => [
                                'mid' => $mid,
                                'text' => $text,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UNIT TESTS: WebhookIdempotencyService
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Тест: генерация event_key из message.mid.
     */
    public function test_generates_event_key_from_message_mid(): void
    {
        $payload = $this->createPayload('m_UNIQUE_MESSAGE_ID_123');

        $eventKey = $this->idempotency->generateEventKey('meta', $payload);

        $this->assertNotNull($eventKey);
        $this->assertEquals(64, strlen($eventKey)); // SHA256 = 64 hex chars
    }

    /**
     * Тест: одинаковый mid генерирует одинаковый event_key.
     */
    public function test_same_mid_generates_same_event_key(): void
    {
        $payload1 = $this->createPayload('m_SAME_MID', '111', 'Hello');
        $payload2 = $this->createPayload('m_SAME_MID', '222', 'Different text');

        $key1 = $this->idempotency->generateEventKey('meta', $payload1);
        $key2 = $this->idempotency->generateEventKey('meta', $payload2);

        $this->assertEquals($key1, $key2, 'Одинаковый mid должен давать одинаковый ключ');
    }

    /**
     * Тест: разный mid генерирует разный event_key.
     */
    public function test_different_mid_generates_different_event_key(): void
    {
        $payload1 = $this->createPayload('m_MID_1');
        $payload2 = $this->createPayload('m_MID_2');

        $key1 = $this->idempotency->generateEventKey('meta', $payload1);
        $key2 = $this->idempotency->generateEventKey('meta', $payload2);

        $this->assertNotEquals($key1, $key2, 'Разный mid должен давать разный ключ');
    }

    /**
     * Тест: первый вызов не считается дубликатом.
     */
    public function test_first_call_is_not_duplicate(): void
    {
        $payload = $this->createPayload('m_FIRST_CALL_'.uniqid());

        $isDuplicate = $this->idempotency->isDuplicate('meta', $payload);

        $this->assertFalse($isDuplicate);
    }

    /**
     * Тест: после markAsProcessed событие считается дубликатом.
     */
    public function test_after_mark_processed_is_duplicate(): void
    {
        $payload = $this->createPayload('m_MARKED_'.uniqid());

        // Первый раз — не дубликат
        $this->assertFalse($this->idempotency->isDuplicate('meta', $payload));

        // Отмечаем как обработанное
        $this->idempotency->markAsProcessed('meta', $payload, '127.0.0.1');

        // Теперь — дубликат
        $this->assertTrue($this->idempotency->isDuplicate('meta', $payload));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INTEGRATION TESTS: Full Webhook Flow
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Тест: повторный webhook с тем же mid не создаёт дубль job.
     */
    public function test_duplicate_webhook_does_not_dispatch_job(): void
    {
        Queue::fake();

        $mid = 'm_DUPLICATE_TEST_'.uniqid();
        $payload = $this->createPayload($mid);
        $payloadJson = json_encode($payload);
        $signature = $this->generateSignature($payloadJson);

        // Первый запрос — должен dispatch
        $response1 = $this->postJson('/api/webhooks/meta', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);
        $response1->assertStatus(200);
        $response1->assertSee('EVENT_RECEIVED');

        // Второй запрос с тем же payload — должен быть пропущен
        $response2 = $this->postJson('/api/webhooks/meta', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);
        $response2->assertStatus(200);
        $response2->assertSee('DUPLICATE_IGNORED');

        // Job должен быть dispatch'нут только один раз
        Queue::assertPushed(\App\Jobs\ProcessMetaMessage::class, 1);
    }

    /**
     * Тест: разные mid создают разные jobs.
     */
    public function test_different_mids_dispatch_different_jobs(): void
    {
        Queue::fake();

        $payload1 = $this->createPayload('m_UNIQUE_1_'.uniqid());
        $payload2 = $this->createPayload('m_UNIQUE_2_'.uniqid());

        $json1 = json_encode($payload1);
        $json2 = json_encode($payload2);

        $this->postJson('/api/webhooks/meta', $payload1, [
            'X-Hub-Signature-256' => $this->generateSignature($json1),
        ])->assertStatus(200);

        $this->postJson('/api/webhooks/meta', $payload2, [
            'X-Hub-Signature-256' => $this->generateSignature($json2),
        ])->assertStatus(200);

        // Два разных job'а
        Queue::assertPushed(\App\Jobs\ProcessMetaMessage::class, 2);
    }

    /**
     * Тест: event_key сохраняется в webhook_logs.
     */
    public function test_event_key_saved_to_webhook_logs(): void
    {
        Queue::fake();

        $mid = 'm_SAVED_KEY_'.uniqid();
        $payload = $this->createPayload($mid);
        $payloadJson = json_encode($payload);
        $signature = $this->generateSignature($payloadJson);

        $this->postJson('/api/webhooks/meta', $payload, [
            'X-Hub-Signature-256' => $signature,
        ])->assertStatus(200);

        // Проверяем, что event_key сохранён
        $log = WebhookLog::where('source', 'meta')->latest()->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->event_key);
        $this->assertEquals(64, strlen($log->event_key));
    }

    /**
     * Тест: дубликат определяется даже после перезапуска (из БД).
     */
    public function test_duplicate_detected_from_database(): void
    {
        Queue::fake();

        $mid = 'm_DB_CHECK_'.uniqid();
        $payload = $this->createPayload($mid);
        $eventKey = $this->idempotency->generateEventKey('meta', $payload);

        // Записываем напрямую в БД (симуляция предыдущей обработки)
        WebhookLog::create([
            'source' => 'meta',
            'event_type' => 'message',
            'event_key' => $eventKey,
            'payload' => $payload,
            'ip_address' => '127.0.0.1',
            'response_code' => 200,
            'processed_at' => now(),
        ]);

        // Очищаем Redis (симуляция перезапуска)
        try {
            Redis::del("webhook:idempotency:meta:{$eventKey}");
        } catch (\Exception $e) {
            // Redis может быть недоступен
        }

        // Проверяем, что дубликат определяется из БД
        $this->assertTrue($this->idempotency->isDuplicate('meta', $payload));
    }

    /**
     * Тест: postback событие также идемпотентно.
     */
    public function test_postback_idempotency(): void
    {
        Queue::fake();

        $timestamp = time() * 1000;
        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'page_123',
                    'time' => $timestamp,
                    'messaging' => [
                        [
                            'sender' => ['id' => '123456'],
                            'recipient' => ['id' => 'page_123'],
                            'timestamp' => $timestamp,
                            'postback' => [
                                'payload' => 'GET_STARTED',
                                'title' => 'Get Started',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $json = json_encode($payload);
        $signature = $this->generateSignature($json);

        // Первый запрос
        $this->postJson('/api/webhooks/meta', $payload, [
            'X-Hub-Signature-256' => $signature,
        ])->assertStatus(200)->assertSee('EVENT_RECEIVED');

        // Второй запрос (дубликат)
        $this->postJson('/api/webhooks/meta', $payload, [
            'X-Hub-Signature-256' => $signature,
        ])->assertStatus(200)->assertSee('DUPLICATE_IGNORED');

        Queue::assertPushed(\App\Jobs\ProcessMetaMessage::class, 1);
    }
}
