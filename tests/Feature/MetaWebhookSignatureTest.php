<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тесты верификации подписи Meta Webhook (X-Hub-Signature-256).
 */
class MetaWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected string $appSecret = 'test_app_secret_123';

    protected string $webhookUrl = '/api/webhooks/meta';

    protected function setUp(): void
    {
        parent::setUp();

        // Устанавливаем App Secret в настройках
        Setting::set('meta_app_secret', $this->appSecret);
    }

    /**
     * Генерирует валидную подпись для payload.
     */
    protected function generateSignature(string $payload): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, $this->appSecret);
    }

    /**
     * Тест: валидная подпись — запрос проходит.
     */
    public function test_valid_signature_allows_request(): void
    {
        $payload = json_encode([
            'object' => 'page',
            'entry' => [
                [
                    'id' => '123456789',
                    'time' => time(),
                    'messaging' => [],
                ],
            ],
        ]);

        $signature = $this->generateSignature($payload);

        $response = $this->postJson(
            $this->webhookUrl,
            json_decode($payload, true),
            [
                'X-Hub-Signature-256' => $signature,
                'Content-Type' => 'application/json',
            ]
        );

        // Ожидаем 200 OK (или какой-то успешный статус, не 403)
        $response->assertStatus(200);
    }

    /**
     * Тест: невалидная подпись — запрос отклоняется с 403.
     */
    public function test_invalid_signature_returns_403(): void
    {
        $payload = json_encode([
            'object' => 'page',
            'entry' => [
                [
                    'id' => '123456789',
                    'time' => time(),
                    'messaging' => [],
                ],
            ],
        ]);

        $invalidSignature = 'sha256=invalid_signature_here_totally_wrong';

        $response = $this->postJson(
            $this->webhookUrl,
            json_decode($payload, true),
            [
                'X-Hub-Signature-256' => $invalidSignature,
                'Content-Type' => 'application/json',
            ]
        );

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Forbidden',
            'code' => 'invalid_signature',
        ]);
    }

    /**
     * Тест: отсутствует заголовок подписи — запрос отклоняется с 403.
     */
    public function test_missing_signature_returns_403(): void
    {
        $payload = [
            'object' => 'page',
            'entry' => [],
        ];

        $response = $this->postJson(
            $this->webhookUrl,
            $payload,
            [
                'Content-Type' => 'application/json',
                // Без X-Hub-Signature-256
            ]
        );

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Forbidden',
            'code' => 'missing_signature',
        ]);
    }

    /**
     * Тест: подпись не совпадает после изменения payload.
     */
    public function test_tampered_payload_returns_403(): void
    {
        $originalPayload = json_encode(['object' => 'page', 'entry' => []]);
        $signature = $this->generateSignature($originalPayload);

        // Изменённый payload (атака)
        $tamperedPayload = ['object' => 'page', 'entry' => [['id' => 'hacked']]];

        $response = $this->postJson(
            $this->webhookUrl,
            $tamperedPayload,
            [
                'X-Hub-Signature-256' => $signature,
                'Content-Type' => 'application/json',
            ]
        );

        $response->assertStatus(403);
        $response->assertJson([
            'code' => 'invalid_signature',
        ]);
    }

    /**
     * Тест: GET запрос (верификация) проходит без подписи.
     */
    public function test_get_verification_request_passes_without_signature(): void
    {
        // Устанавливаем verify token
        Setting::set('meta_webhook_verify_token', 'my_verify_token');

        $response = $this->get($this->webhookUrl.'?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'my_verify_token',
            'hub_challenge' => 'challenge_123',
        ]));

        $response->assertStatus(200);
        $response->assertSee('challenge_123');
    }

    /**
     * Тест: если App Secret не настроен — запрос пропускается (backward compatibility).
     */
    public function test_missing_app_secret_allows_request_with_warning(): void
    {
        // Удаляем App Secret
        Setting::where('key', 'meta_app_secret')->delete();
        Setting::clearCache();

        $payload = [
            'object' => 'page',
            'entry' => [],
        ];

        $response = $this->postJson(
            $this->webhookUrl,
            $payload,
            [
                'X-Hub-Signature-256' => 'sha256=any_signature',
                'Content-Type' => 'application/json',
            ]
        );

        // Должен пропустить (backward compatibility)
        $response->assertStatus(200);
    }

    /**
     * Тест: при невалидной подписи jobs НЕ dispatch'атся.
     */
    public function test_invalid_signature_does_not_dispatch_jobs(): void
    {
        // Используем fake для очереди
        \Illuminate\Support\Facades\Queue::fake();

        $payload = json_encode([
            'object' => 'page',
            'entry' => [
                [
                    'id' => '123456789',
                    'time' => time(),
                    'messaging' => [
                        [
                            'sender' => ['id' => '111'],
                            'recipient' => ['id' => '222'],
                            'message' => ['text' => 'Hello'],
                        ],
                    ],
                ],
            ],
        ]);

        $invalidSignature = 'sha256=wrong';

        $response = $this->postJson(
            $this->webhookUrl,
            json_decode($payload, true),
            [
                'X-Hub-Signature-256' => $invalidSignature,
            ]
        );

        $response->assertStatus(403);

        // Проверяем, что ProcessMetaMessage НЕ был отправлен в очередь
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    }

    /**
     * Тест: при валидной подписи jobs dispatch'атся.
     */
    public function test_valid_signature_dispatches_jobs(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $payload = json_encode([
            'object' => 'page',
            'entry' => [
                [
                    'id' => '123456789',
                    'time' => time(),
                    'messaging' => [
                        [
                            'sender' => ['id' => '111'],
                            'recipient' => ['id' => '222'],
                            'timestamp' => time() * 1000,
                            'message' => ['mid' => 'm_123', 'text' => 'Hello'],
                        ],
                    ],
                ],
            ],
        ]);

        $signature = $this->generateSignature($payload);

        $response = $this->postJson(
            $this->webhookUrl,
            json_decode($payload, true),
            [
                'X-Hub-Signature-256' => $signature,
            ]
        );

        $response->assertStatus(200);

        // Проверяем, что ProcessMetaMessage был отправлен в очередь
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ProcessMetaMessage::class);
    }
}
