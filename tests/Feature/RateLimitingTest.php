<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Тесты Rate Limiting для API endpoints.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Очищаем rate limiter перед каждым тестом
        RateLimiter::clear('webhook:127.0.0.1');
        RateLimiter::clear('test:127.0.0.1');
    }

    /**
     * Тест: webhook endpoint возвращает 429 при превышении лимита.
     */
    public function test_webhook_rate_limit_returns_429(): void
    {
        // Настраиваем app secret для прохождения signature check
        Setting::set('meta_app_secret', 'test_secret');

        $payload = json_encode(['object' => 'page', 'entry' => []]);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_secret');

        // Делаем 301 запрос (лимит 300)
        for ($i = 0; $i < 301; $i++) {
            $response = $this->postJson('/api/webhooks/meta',
                json_decode($payload, true),
                ['X-Hub-Signature-256' => $signature]
            );

            // Первые 300 должны пройти
            if ($i < 300) {
                $this->assertNotEquals(429, $response->status(), "Request {$i} should not be rate limited");
            }
        }

        // 301-й запрос должен вернуть 429
        $response = $this->postJson('/api/webhooks/meta',
            json_decode($payload, true),
            ['X-Hub-Signature-256' => $signature]
        );

        $response->assertStatus(429);
        $response->assertJson(['error' => 'Too Many Requests']);
    }

    /**
     * Тест: test endpoint имеет более низкий лимит (10/min).
     */
    public function test_test_endpoint_rate_limit(): void
    {
        // Делаем 11 запросов (лимит 10)
        for ($i = 0; $i < 11; $i++) {
            $response = $this->postJson('/api/test/incoming-meta', [
                'object' => 'page',
                'entry' => [],
            ]);
        }

        // 11-й запрос должен вернуть 429
        $response->assertStatus(429);
    }

    /**
     * Тест: health check исключён из rate limiting.
     */
    public function test_health_check_excluded_from_rate_limit(): void
    {
        // Делаем много запросов
        for ($i = 0; $i < 20; $i++) {
            $response = $this->get('/api/test/health');
            $response->assertStatus(200);
        }
    }

    /**
     * Тест: Meta verification (GET) исключён из rate limiting.
     */
    public function test_meta_verification_excluded_from_rate_limit(): void
    {
        Setting::set('meta_webhook_verify_token', 'test_token');

        // Делаем много запросов
        for ($i = 0; $i < 20; $i++) {
            $response = $this->get('/api/webhooks/meta?'.http_build_query([
                'hub_mode' => 'subscribe',
                'hub_verify_token' => 'test_token',
                'hub_challenge' => 'challenge_'.$i,
            ]));
            $response->assertStatus(200);
        }
    }

    /**
     * Тест: response содержит Retry-After header.
     */
    public function test_rate_limit_response_contains_retry_after(): void
    {
        // Исчерпываем лимит для test endpoint (быстрее)
        for ($i = 0; $i < 11; $i++) {
            $this->postJson('/api/test/incoming-meta', ['object' => 'page']);
        }

        $response = $this->postJson('/api/test/incoming-meta', ['object' => 'page']);

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
        // Note: JSON body format may vary by Laravel version, header is sufficient
    }

    /**
     * Тест: rate limit работает стабильно с/без подписи.
     */
    public function test_rate_limit_stable_with_signature_validation(): void
    {
        Setting::set('meta_app_secret', 'test_secret');

        // Запрос с валидной подписью
        $payload1 = json_encode(['object' => 'page', 'entry' => []]);
        $signature1 = 'sha256='.hash_hmac('sha256', $payload1, 'test_secret');

        $response1 = $this->postJson('/api/webhooks/meta',
            json_decode($payload1, true),
            ['X-Hub-Signature-256' => $signature1]
        );
        $response1->assertStatus(200);

        // Запрос с невалидной подписью (403, но rate limit всё равно считается)
        $response2 = $this->postJson('/api/webhooks/meta',
            ['object' => 'page', 'entry' => []],
            ['X-Hub-Signature-256' => 'sha256=invalid']
        );
        $response2->assertStatus(403);

        // Rate limit headers должны присутствовать
        $this->assertTrue(
            $response1->headers->has('X-RateLimit-Remaining') ||
            $response1->headers->has('X-Ratelimit-Remaining'),
            'Rate limit headers should be present'
        );
    }
}
