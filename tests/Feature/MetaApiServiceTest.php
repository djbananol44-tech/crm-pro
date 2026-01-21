<?php

namespace Tests\Feature;

use App\Services\MetaApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MetaApiService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Настройки через Setting model (Zero-Config)
        \App\Models\Setting::set('meta_page_id', 'test_page_123');
        \App\Models\Setting::set('meta_access_token', 'test_access_token');

        $this->service = new MetaApiService;
    }

    public function test_get_conversations_returns_array(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [
                    [
                        'id' => 't_123456789',
                        'updated_time' => '2024-01-15T10:30:00+0000',
                        'participants' => [
                            'data' => [
                                ['id' => 'test_page_123', 'name' => 'Page'],
                                ['id' => '987654321', 'name' => 'User'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $conversations = $this->service->getConversations();

        $this->assertIsArray($conversations);
        $this->assertCount(1, $conversations);
        $this->assertEquals('t_123456789', $conversations[0]['id']);
    }

    public function test_get_user_profile_returns_user_data(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => '987654321',
                'first_name' => 'Иван',
                'last_name' => 'Петров',
                'name' => 'Иван Петров',
            ], 200),
        ]);

        $profile = $this->service->getUserProfile('987654321');

        $this->assertEquals('Иван', $profile['first_name']);
        $this->assertEquals('Петров', $profile['last_name']);
        $this->assertEquals('Иван Петров', $profile['name']);
    }

    public function test_build_conversation_link(): void
    {
        // Test with default platform (messenger)
        $link = $this->service->buildConversationLink('t_123456789');

        $this->assertStringContainsString('business.facebook.com', $link);
        $this->assertStringContainsString('asset_id=test_page_123', $link);
        $this->assertStringContainsString('selected_item_id=t_123456789', $link);
    }

    public function test_build_conversation_link_instagram(): void
    {
        $link = $this->service->buildConversationLink('ig_123456', 'instagram');

        $this->assertStringContainsString('business.facebook.com', $link);
        $this->assertStringContainsString('mailbox_id=instagram', $link);
    }

    public function test_build_conversation_link_fallback_without_page_id(): void
    {
        // Создаём сервис без page_id
        \App\Models\Setting::set('meta_page_id', '');
        $serviceWithoutPageId = new MetaApiService;

        $link = $serviceWithoutPageId->buildConversationLink('t_123456789');

        // Должен вернуть fallback ссылку
        $this->assertEquals(
            'https://www.facebook.com/messages/t/t_123456789',
            $link
        );
    }

    public function test_extract_participant_psid(): void
    {
        // Участники: page + user, метод должен вернуть ID user (не page)
        $conversation = [
            'id' => 't_123456789',
            'participants' => [
                'data' => [
                    ['id' => 'test_page_123', 'name' => 'Page'],  // page_id из setUp
                    ['id' => '987654321', 'name' => 'User'],
                ],
            ],
        ];

        $psid = $this->service->extractParticipantPsid($conversation);

        $this->assertEquals('987654321', $psid);
    }

    public function test_detect_platform_defaults_to_messenger(): void
    {
        $conversation = ['id' => 't_123'];

        $platform = $this->service->detectPlatform($conversation);

        $this->assertEquals('messenger', $platform);
    }

    public function test_detect_platform_returns_instagram(): void
    {
        $conversation = ['id' => 't_123', 'platform' => 'INSTAGRAM'];

        $platform = $this->service->detectPlatform($conversation);

        $this->assertEquals('instagram', $platform);
    }
}
