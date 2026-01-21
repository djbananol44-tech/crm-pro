<?php

namespace Tests\Feature;

use App\Services\MetaApiService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * JGGL CRM — Message Limit Policy Test
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * Тест: система хранит и показывает только последние 20 сообщений.
 * Политика Meta Platform ограничивает доступ к глубокой истории.
 */
class MessageLimitTest extends TestCase
{
    #[Test]
    public function max_messages_constant_is_20(): void
    {
        $this->assertEquals(20, MetaApiService::MAX_MESSAGES_PER_CONVERSATION);
        $this->assertEquals(20, MetaApiService::getMaxMessagesLimit());
    }

    #[Test]
    public function requesting_more_than_20_messages_returns_only_20(): void
    {
        $mockMessages = [];
        for ($i = 1; $i <= 25; $i++) {
            $mockMessages[] = [
                'id' => "msg_{$i}",
                'message' => "Message #{$i}",
                'created_time' => now()->subMinutes(25 - $i)->toIso8601String(),
                'from' => ['id' => 'user_123'],
            ];
        }

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => $mockMessages,
            ], 200),
        ]);

        $this->mockMetaSettings();

        $service = app(MetaApiService::class);

        $messages = $service->getMessages('conv_123', 50);

        $this->assertCount(20, $messages);

        $this->assertEquals('msg_1', $messages[0]['id']);
        $this->assertEquals('msg_20', $messages[19]['id']);
    }

    #[Test]
    public function requesting_up_to_20_messages_returns_requested_amount(): void
    {
        $mockMessages = [];
        for ($i = 1; $i <= 15; $i++) {
            $mockMessages[] = [
                'id' => "msg_{$i}",
                'message' => "Message #{$i}",
                'created_time' => now()->subMinutes(15 - $i)->toIso8601String(),
                'from' => ['id' => 'user_123'],
            ];
        }

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => $mockMessages,
            ], 200),
        ]);

        $this->mockMetaSettings();

        $service = app(MetaApiService::class);

        $messages = $service->getMessages('conv_123', 15);

        $this->assertCount(15, $messages);
    }

    #[Test]
    public function default_limit_is_20(): void
    {
        Http::fake(function ($request) {
            $query = $request->data();

            $this->assertEquals(20, $query['limit'] ?? null);

            return Http::response(['data' => []], 200);
        });

        $this->mockMetaSettings();

        $service = app(MetaApiService::class);

        $service->getMessages('conv_123');

        Http::assertSentCount(1);
    }

    #[Test]
    public function limit_is_capped_at_20_in_http_request(): void
    {
        Http::fake(function ($request) {
            $query = $request->data();

            $this->assertEquals(20, $query['limit'] ?? null);

            return Http::response(['data' => []], 200);
        });

        $this->mockMetaSettings();

        $service = app(MetaApiService::class);

        $service->getMessages('conv_123', 100);

        Http::assertSentCount(1);
    }

    protected function mockMetaSettings(): void
    {
        \App\Models\Setting::set('meta_page_id', 'test_page_123');
        \App\Models\Setting::set('meta_access_token', 'test_token_abc');
    }
}
