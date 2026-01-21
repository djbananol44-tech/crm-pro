<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiAnalysisService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Контроллер для тестирования вебхуков и интеграций.
 *
 * ⚠️ Только для разработки и тестирования!
 */
class TestWebhookController extends Controller
{
    /**
     * Эмуляция входящего сообщения от Meta (Facebook/Instagram).
     *
     * Создает тестовую сделку и запускает всю цепочку:
     * Contact → Conversation → Deal → Telegram → AI
     */
    public function simulateMetaIncoming(Request $request): JsonResponse
    {
        // Только в режиме отладки или по специальному ключу
        if (!config('app.debug') && $request->header('X-Test-Key') !== config('app.key')) {
            return response()->json(['error' => 'Test mode disabled'], 403);
        }

        Log::info('TestWebhook: Симуляция входящего сообщения от Meta');

        $results = [
            'steps' => [],
            'success' => true,
        ];

        try {
            // 1. Создаем тестовый контакт
            $psid = 'test_'.Str::random(10);
            $contact = Contact::create([
                'psid' => $psid,
                'first_name' => 'Тестовый',
                'last_name' => 'Клиент '.now()->format('H:i'),
                'name' => 'Тестовый Клиент '.now()->format('H:i'),
            ]);
            $results['steps'][] = [
                'step' => '1. Контакт',
                'status' => '✅',
                'message' => "Создан: {$contact->name} (PSID: {$psid})",
            ];

            // 2. Создаем беседу
            $conversation = Conversation::create([
                'conversation_id' => 't:'.Str::random(20),
                'contact_id' => $contact->id,
                'platform' => 'messenger',
                'updated_time' => now(),
                'link' => 'https://business.facebook.com/latest/inbox/all',
            ]);
            $results['steps'][] = [
                'step' => '2. Беседа',
                'status' => '✅',
                'message' => "Создана для платформы: {$conversation->platform}",
            ];

            // 3. Создаем сделку
            $deal = Deal::create([
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
                'status' => 'New',
                'is_priority' => true,
                'last_client_message_at' => now(),
            ]);
            $results['steps'][] = [
                'step' => '3. Сделка',
                'status' => '✅',
                'message' => "Создана: #{$deal->id} (Приоритетная)",
            ];

            // 4. Отправляем уведомление в Telegram
            $telegramResult = $this->sendTelegramNotification($deal, $contact);
            $results['steps'][] = $telegramResult;

            // 5. Запрашиваем AI анализ
            $aiResult = $this->requestAiAnalysis($deal);
            $results['steps'][] = $aiResult;

            // Итоговый результат
            $results['deal'] = [
                'id' => $deal->id,
                'url' => url("/deals/{$deal->id}"),
                'admin_url' => url("/admin/deals/{$deal->id}"),
            ];

            Log::info('TestWebhook: Симуляция завершена успешно', [
                'deal_id' => $deal->id,
            ]);

        } catch (\Exception $e) {
            Log::error('TestWebhook: Ошибка симуляции', [
                'error' => $e->getMessage(),
            ]);

            $results['success'] = false;
            $results['error'] = $e->getMessage();
        }

        return response()->json($results);
    }

    /**
     * Отправить тестовое уведомление в Telegram.
     */
    private function sendTelegramNotification(Deal $deal, Contact $contact): array
    {
        try {
            $telegram = app(TelegramService::class);

            if (!$telegram->isAvailable()) {
                return [
                    'step' => '4. Telegram',
                    'status' => '⚠️',
                    'message' => 'Бот не настроен',
                ];
            }

            // Находим админа с telegram_chat_id
            $admin = User::where('role', 'admin')
                ->whereNotNull('telegram_chat_id')
                ->first();

            if (!$admin) {
                // Пробуем найти любого пользователя с chat_id
                $admin = User::whereNotNull('telegram_chat_id')->first();
            }

            if (!$admin || !$admin->telegram_chat_id) {
                return [
                    'step' => '4. Telegram',
                    'status' => '⚠️',
                    'message' => 'Нет пользователей с telegram_chat_id',
                ];
            }

            $telegram->sendNewLeadNotification($admin, $deal);

            return [
                'step' => '4. Telegram',
                'status' => '✅',
                'message' => "Уведомление отправлено: @{$admin->name}",
            ];

        } catch (\Exception $e) {
            return [
                'step' => '4. Telegram',
                'status' => '❌',
                'message' => 'Ошибка: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Запросить AI анализ.
     */
    private function requestAiAnalysis(Deal $deal): array
    {
        try {
            $aiService = app(AiAnalysisService::class);

            if (!$aiService->isAvailable()) {
                return [
                    'step' => '5. Gemini AI',
                    'status' => '⚠️',
                    'message' => 'AI не настроен или отключен',
                ];
            }

            // Тестовые сообщения для анализа
            $testMessages = collect([
                (object) [
                    'message' => 'Здравствуйте! Хочу узнать цену на ваш товар.',
                    'from' => (object) ['id' => 'client'],
                    'created_time' => now()->subMinutes(5)->toISOString(),
                ],
                (object) [
                    'message' => 'Добрый день! Конечно, подскажите какой именно товар вас интересует?',
                    'from' => (object) ['id' => 'page'],
                    'created_time' => now()->subMinutes(3)->toISOString(),
                ],
                (object) [
                    'message' => 'Меня интересует доставка в Москву и возможность оплаты картой.',
                    'from' => (object) ['id' => 'client'],
                    'created_time' => now()->toISOString(),
                ],
            ]);

            $summary = $aiService->getConversationSummary($testMessages);
            $score = $aiService->getLeadScore($testMessages);

            if ($summary) {
                $deal->update([
                    'ai_summary' => $summary,
                    'ai_score' => $score,
                    'ai_summary_at' => now(),
                ]);

                return [
                    'step' => '5. Gemini AI',
                    'status' => '✅',
                    'message' => "Анализ получен, Score: {$score}",
                ];
            }

            return [
                'step' => '5. Gemini AI',
                'status' => '⚠️',
                'message' => 'Пустой ответ от AI',
            ];

        } catch (\Exception $e) {
            return [
                'step' => '5. Gemini AI',
                'status' => '❌',
                'message' => 'Ошибка: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Расширенная проверка статуса всех интеграций и системы.
     */
    public function healthCheck(): JsonResponse
    {
        $checks = [];
        $overallStatus = 'ok';

        // Database
        try {
            \DB::connection()->getPdo();
            $dbVersion = \DB::selectOne('SELECT version()')->version ?? 'Unknown';
            $checks['database'] = [
                'status' => 'ok',
                'message' => 'Connected',
                'details' => substr($dbVersion, 0, 30),
            ];
        } catch (\Exception $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
            $overallStatus = 'error';
        }

        // Redis
        try {
            \Illuminate\Support\Facades\Redis::ping();
            $checks['redis'] = ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Exception $e) {
            $checks['redis'] = [
                'status' => config('queue.default') === 'redis' ? 'error' : 'warning',
                'message' => $e->getMessage(),
            ];
            if (config('queue.default') === 'redis') {
                $overallStatus = 'error';
            }
        }

        // Queue
        $queueMetrics = $this->getQueueMetrics();
        $queueStatus = 'ok';
        $queueMessage = 'Working';

        if ($queueMetrics['failed'] > 0) {
            $queueStatus = 'warning';
            $queueMessage = "{$queueMetrics['failed']} failed jobs";
            if ($overallStatus === 'ok') {
                $overallStatus = 'warning';
            }
        }

        $totalPending = array_sum($queueMetrics['queues']);
        if ($totalPending > 100) {
            $queueStatus = 'warning';
            $queueMessage = "Queue backlog: {$totalPending}";
            if ($overallStatus === 'ok') {
                $overallStatus = 'warning';
            }
        }

        $checks['queue'] = [
            'status' => $queueStatus,
            'message' => $queueMessage,
            'metrics' => $queueMetrics,
        ];

        // Meta API
        $metaToken = Setting::get('meta_access_token');
        $checks['meta'] = [
            'status' => $metaToken ? 'configured' : 'not_configured',
            'message' => $metaToken ? 'Token set' : 'No token',
        ];

        // Telegram
        $tgToken = Setting::get('telegram_bot_token');
        $tgMode = Setting::get('telegram_mode', 'polling');
        $checks['telegram'] = [
            'status' => $tgToken ? 'configured' : 'not_configured',
            'message' => $tgToken ? "Token set ({$tgMode})" : 'No token',
            'mode' => $tgMode,
        ];

        // Gemini
        $geminiKey = Setting::get('gemini_api_key');
        $aiEnabled = Setting::get('ai_enabled');
        $checks['gemini'] = [
            'status' => $geminiKey && $aiEnabled ? 'enabled' : ($geminiKey ? 'disabled' : 'not_configured'),
            'message' => $geminiKey ? ($aiEnabled ? 'Enabled' : 'Disabled') : 'No key',
        ];

        // Stats
        $stats = [
            'deals_total' => Deal::count(),
            'deals_active' => Deal::whereIn('status', ['New', 'In Progress'])->count(),
            'deals_today' => Deal::whereDate('created_at', today())->count(),
            'contacts_total' => Contact::count(),
        ];

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
            'stats' => $stats,
        ]);
    }

    /**
     * Получить метрики очередей.
     */
    protected function getQueueMetrics(): array
    {
        $metrics = [
            'driver' => config('queue.default'),
            'queues' => [
                'default' => 0,
                'meta' => 0,
                'ai' => 0,
            ],
            'failed' => 0,
        ];

        try {
            // Для Redis
            if (config('queue.default') === 'redis') {
                $prefix = config('database.redis.options.prefix', '');

                foreach (array_keys($metrics['queues']) as $queue) {
                    try {
                        $key = $prefix."queues:{$queue}";
                        $metrics['queues'][$queue] = (int) \Illuminate\Support\Facades\Redis::llen($key);
                    } catch (\Exception $e) {
                        // Игнорируем
                    }
                }
            }

            // Failed jobs из БД
            $metrics['failed'] = \DB::table('failed_jobs')->count();

        } catch (\Exception $e) {
            // Игнорируем
        }

        return $metrics;
    }
}
