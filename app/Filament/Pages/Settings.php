<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class Settings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Настройки';

    protected static ?string $title = 'Настройки системы';

    protected static ?string $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    /**
     * Placeholder для masked полей.
     * Если пользователь не меняет значение, оставляем старое.
     */
    protected const MASKED_PLACEHOLDER = '••••••••';

    public function mount(): void
    {
        // Для секретных полей показываем placeholder если значение есть
        $this->form->fill([
            // Meta API — несекретные
            'meta_page_id' => Setting::get('meta_page_id', ''),
            'meta_app_id' => Setting::get('meta_app_id', ''),

            // Meta API — секретные (masked)
            'meta_access_token' => $this->getMaskedOrEmpty('meta_access_token'),
            'meta_webhook_verify_token' => $this->getMaskedOrEmpty('meta_webhook_verify_token'),
            'meta_app_secret' => $this->getMaskedOrEmpty('meta_app_secret'),

            // AI Integration
            'gemini_api_key' => $this->getMaskedOrEmpty('gemini_api_key'),
            'ai_enabled' => Setting::get('ai_enabled', false),

            // Telegram
            'telegram_bot_token' => $this->getMaskedOrEmpty('telegram_bot_token'),
            'telegram_mode' => Setting::get('telegram_mode', 'polling'),
        ]);
    }

    /**
     * Получить masked значение или пустую строку.
     */
    protected function getMaskedOrEmpty(string $key): string
    {
        return Setting::hasValue($key) ? self::MASKED_PLACEHOLDER : '';
    }

    /**
     * Проверить, нужно ли обновлять секретное поле.
     * Если значение = placeholder, значит пользователь не менял его.
     */
    protected function shouldUpdateSecret(string $key, ?string $newValue): bool
    {
        // Пустая строка = очистить
        if ($newValue === '' || $newValue === null) {
            return true;
        }

        // Placeholder = не менять
        if ($newValue === self::MASKED_PLACEHOLDER) {
            return false;
        }

        // Любое другое значение = обновить
        return true;
    }

    /**
     * Получить текст подсказки для секретного поля.
     */
    protected function getSecretHelperText(string $key, string $description = ''): string
    {
        $hasValue = Setting::hasValue($key);
        $status = $hasValue
            ? '🔒 Установлено (оставьте пустым чтобы сохранить текущее)'
            : '⚠️ Не установлено';

        return $description ? "{$description}. {$status}" : $status;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Meta Business Suite')
                    ->description('Интеграция с Facebook & Instagram Direct')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->iconColor('info')
                    ->collapsible()
                    ->compact()
                    ->schema([
                        Forms\Components\TextInput::make('meta_page_id')
                            ->label('Page ID')
                            ->helperText('ID страницы Facebook')
                            ->placeholder('123456789012345')
                            ->required()
                            ->prefixIcon('heroicon-o-identification'),

                        Forms\Components\Textarea::make('meta_access_token')
                            ->label('Access Token')
                            ->helperText($this->getSecretHelperText('meta_access_token', 'Долгосрочный токен доступа страницы'))
                            ->placeholder('EAAxxxxxxx... (оставьте пустым чтобы не менять)')
                            ->rows(2),

                        Forms\Components\Fieldset::make('Дополнительно')
                            ->schema([
                                Forms\Components\TextInput::make('meta_app_id')
                                    ->label('App ID')
                                    ->placeholder('123456789012345')
                                    ->prefixIcon('heroicon-o-cube'),

                                Forms\Components\TextInput::make('meta_app_secret')
                                    ->label('App Secret')
                                    ->helperText($this->getSecretHelperText('meta_app_secret'))
                                    ->placeholder('Введите новый секрет или оставьте пустым')
                                    ->password()
                                    ->revealable()
                                    ->prefixIcon('heroicon-o-key'),
                            ])
                            ->columns(2),

                        Forms\Components\TextInput::make('meta_webhook_verify_token')
                            ->label('Webhook Verify Token')
                            ->helperText($this->getSecretHelperText('meta_webhook_verify_token', 'Токен для верификации webhook'))
                            ->placeholder('Введите токен или оставьте пустым')
                            ->password()
                            ->revealable()
                            ->prefixIcon('heroicon-o-shield-check'),
                    ]),

                Forms\Components\Section::make('Telegram уведомления')
                    ->description('Мгновенные уведомления менеджерам в Telegram')
                    ->icon('heroicon-o-paper-airplane')
                    ->iconColor('info')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('telegram_bot_token')
                            ->label('Bot Token')
                            ->helperText($this->getSecretHelperText('telegram_bot_token', 'Токен бота от @BotFather'))
                            ->placeholder('123456789:ABCdefGHIjklMNOpqrsTUVwxyz')
                            ->password()
                            ->revealable()
                            ->prefixIcon('heroicon-o-key'),

                        Forms\Components\Select::make('telegram_mode')
                            ->label('Режим работы бота')
                            ->options([
                                'webhook' => '🔗 Webhook (требует HTTPS)',
                                'polling' => '🔄 Long Polling (bot_worker)',
                            ])
                            ->default('polling')
                            ->helperText('Webhook: бот получает обновления через HTTPS. Polling: фоновый процесс опрашивает Telegram API.')
                            ->native(false),

                        Forms\Components\View::make('filament.components.telegram-info'),
                    ]),

                Forms\Components\Section::make('AI-Ассистент (Gemini)')
                    ->description('Автоматический анализ переписки и Lead Scoring')
                    ->icon('heroicon-o-sparkles')
                    ->iconColor('warning')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Toggle::make('ai_enabled')
                            ->label('Включить AI-ассистента')
                            ->helperText('Активирует функции анализа переписки и оценки лидов')
                            ->onColor('success')
                            ->offColor('gray'),

                        Forms\Components\TextInput::make('gemini_api_key')
                            ->label('Gemini API Key')
                            ->helperText($this->getSecretHelperText('gemini_api_key', 'Получите ключ на ai.google.dev'))
                            ->placeholder('AIzaSy...')
                            ->password()
                            ->revealable()
                            ->prefixIcon('heroicon-o-key'),

                        Forms\Components\View::make('filament.components.gemini-info'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $userId = auth()->id();
        $updatedSecrets = [];
        $telegramTokenChanged = false;

        try {
            // Несекретные поля — сохраняем всегда
            Setting::set('meta_page_id', $data['meta_page_id'], $userId);
            Setting::set('meta_app_id', $data['meta_app_id'] ?? '', $userId);
            Setting::set('ai_enabled', $data['ai_enabled'] ? 'true' : 'false', $userId);

            // Telegram mode — сохраняем ДО проверки токена
            $oldMode = Setting::get('telegram_mode', 'polling');
            $newMode = $data['telegram_mode'] ?? 'polling';
            Setting::set('telegram_mode', $newMode, $userId);

            // Секретные поля — сохраняем только если изменились
            $secretFields = [
                'meta_access_token',
                'meta_webhook_verify_token',
                'meta_app_secret',
                'telegram_bot_token',
                'gemini_api_key',
            ];

            foreach ($secretFields as $field) {
                $newValue = $data[$field] ?? '';

                if ($this->shouldUpdateSecret($field, $newValue)) {
                    // Если placeholder — пропускаем
                    if ($newValue === self::MASKED_PLACEHOLDER) {
                        continue;
                    }

                    Setting::set($field, $newValue, $userId);

                    if (!empty($newValue)) {
                        $updatedSecrets[] = $field;

                        // Отмечаем что токен Telegram изменился
                        if ($field === 'telegram_bot_token') {
                            $telegramTokenChanged = true;
                        }
                    }
                }
            }

            Setting::clearCache();

            // Уведомления
            Notification::make()
                ->title('Настройки сохранены')
                ->success()
                ->send();

            if (!empty($updatedSecrets)) {
                Notification::make()
                    ->title('Обновлены секретные ключи')
                    ->body('Изменено: '.count($updatedSecrets).' ключ(ей)')
                    ->icon('heroicon-o-key')
                    ->iconColor('warning')
                    ->send();
            }

            // АВТО-АКТИВАЦИЯ GEMINI
            $geminiKeyChanged = in_array('gemini_api_key', $updatedSecrets);
            if ($geminiKeyChanged || ($data['ai_enabled'] && Setting::hasValue('gemini_api_key'))) {
                $this->autoActivateGemini();
            }

            // АВТО-АКТИВАЦИЯ TELEGRAM
            // Если токен изменился или режим изменился — переактивируем
            if ($telegramTokenChanged || ($oldMode !== $newMode && Setting::hasValue('telegram_bot_token'))) {
                $this->autoActivateTelegram();
            }

        } catch (\Exception $e) {
            Log::error('Settings: Ошибка сохранения', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Ошибка сохранения')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Авто-активация Gemini при сохранении ключа.
     */
    protected function autoActivateGemini(): void
    {
        $apiKey = Setting::get('gemini_api_key');

        if (empty($apiKey)) {
            return;
        }

        try {
            $result = \App\Services\AiAnalysisService::validateAndSetup($apiKey);

            if ($result['success']) {
                Notification::make()
                    ->title('✅ Gemini AI активирован')
                    ->body($result['message'])
                    ->icon('heroicon-o-sparkles')
                    ->iconColor('success')
                    ->duration(10000)
                    ->send();
            } else {
                Notification::make()
                    ->title('❌ Ошибка активации Gemini')
                    ->body($result['message'])
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->duration(10000)
                    ->send();
            }

        } catch (\Exception $e) {
            Log::error('Settings: Ошибка авто-активации Gemini', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('❌ Ошибка Gemini')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Авто-активация Telegram при сохранении токена.
     */
    protected function autoActivateTelegram(): void
    {
        $token = Setting::get('telegram_bot_token');

        if (empty($token)) {
            return;
        }

        try {
            $result = \App\Services\TelegramService::validateAndSetup($token);

            if ($result['success']) {
                Notification::make()
                    ->title('✅ Telegram активирован')
                    ->body($result['message'])
                    ->icon('heroicon-o-paper-airplane')
                    ->iconColor('success')
                    ->duration(10000)
                    ->send();
            } else {
                Notification::make()
                    ->title('❌ Ошибка активации Telegram')
                    ->body($result['message'])
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->duration(10000)
                    ->send();
            }

        } catch (\Exception $e) {
            Log::error('Settings: Ошибка авто-активации Telegram', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('❌ Ошибка Telegram')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function testConnection(): void
    {
        try {
            $metaApi = app(\App\Services\MetaApiService::class);
            $conversations = $metaApi->getConversations();

            Notification::make()
                ->title('Подключение к Meta успешно')
                ->body('Получено бесед: '.count($conversations))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Ошибка подключения к Meta')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function testGemini(): void
    {
        try {
            $ai = app(\App\Services\AiAnalysisService::class);

            if (!$ai->isConfigured()) {
                Notification::make()
                    ->title('API ключ не указан или AI отключен')
                    ->warning()
                    ->send();

                return;
            }

            $result = $ai->checkAndUpdateStatus();

            if ($result['success']) {
                $latency = $result['latency_ms'] ?? 'N/A';

                Notification::make()
                    ->title('✅ Gemini работает')
                    ->body("Latency: {$latency}ms")
                    ->success()
                    ->duration(10000)
                    ->send();
            } else {
                Notification::make()
                    ->title('❌ Ошибка Gemini')
                    ->body($result['message'] ?? $result['last_error'] ?? 'Неизвестная ошибка')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Ошибка Gemini')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Показать текущий статус Gemini интеграции.
     */
    public function showGeminiStatus(): void
    {
        $status = \App\Services\AiAnalysisService::getStatus();

        $statusIcon = match ($status['status']) {
            'ok' => '🟢',
            'error' => '🔴',
            default => '⚪',
        };

        $body = "Статус: {$statusIcon} {$status['status']}\n";
        $body .= 'Включен: '.($status['enabled'] ? 'Да' : 'Нет')."\n";
        $body .= 'Ключ: '.($status['has_key'] ? '✅ установлен' : '❌ нет')."\n";

        if ($status['last_latency_ms']) {
            $body .= "Latency: {$status['last_latency_ms']}ms\n";
        }

        if ($status['last_error']) {
            $body .= "Ошибка: {$status['last_error']}\n";
        }

        if ($status['last_check_at']) {
            $body .= 'Проверка: '.\Carbon\Carbon::parse($status['last_check_at'])->diffForHumans();
        }

        Notification::make()
            ->title('Статус Gemini AI')
            ->body($body)
            ->info()
            ->duration(15000)
            ->send();
    }

    public function testTelegram(): void
    {
        try {
            $telegram = app(\App\Services\TelegramService::class);
            $result = $telegram->checkAndUpdateStatus();

            if ($result['success']) {
                $mode = Setting::get('telegram_mode', 'polling');
                $webhookUrl = $result['webhook_url'] ?? '';

                $body = "@{$result['bot_username']}\n";
                $body .= "Режим: {$mode}\n";

                if ($mode === 'webhook' && $webhookUrl) {
                    $body .= "Webhook: ✅ {$webhookUrl}";
                } elseif ($mode === 'webhook') {
                    $body .= 'Webhook: ⚠️ не установлен';
                }

                Notification::make()
                    ->title('✅ Telegram работает')
                    ->body($body)
                    ->success()
                    ->duration(10000)
                    ->send();
            } else {
                Notification::make()
                    ->title('❌ Ошибка Telegram')
                    ->body($result['message'])
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Ошибка Telegram')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function setTelegramWebhook(): void
    {
        try {
            // Используем авто-активацию с secret_token
            $token = Setting::get('telegram_bot_token');

            if (empty($token)) {
                Notification::make()
                    ->title('Токен бота не указан')
                    ->warning()
                    ->send();

                return;
            }

            // Принудительно ставим webhook mode
            Setting::set('telegram_mode', 'webhook');
            Setting::clearCache();

            $result = \App\Services\TelegramService::validateAndSetup($token);

            if ($result['success']) {
                Notification::make()
                    ->title('✅ Webhook установлен')
                    ->body($result['webhook_url'] ?? url('/api/webhooks/telegram'))
                    ->success()
                    ->duration(10000)
                    ->send();
            } else {
                throw new \Exception($result['message']);
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Ошибка установки webhook')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Показать текущий статус Telegram интеграции.
     */
    public function showTelegramStatus(): void
    {
        $status = \App\Services\TelegramService::getStatus();

        $statusIcon = match ($status['status']) {
            'ok' => '🟢',
            'error' => '🔴',
            default => '⚪',
        };

        $body = "Статус: {$statusIcon} {$status['status']}\n";
        $body .= "Режим: {$status['mode']}\n";

        if ($status['bot_username']) {
            $body .= "Бот: @{$status['bot_username']}\n";
        }

        if ($status['webhook_url']) {
            $body .= "Webhook: {$status['webhook_url']}\n";
        }

        if ($status['last_error']) {
            $body .= "Ошибка: {$status['last_error']}\n";
        }

        if ($status['last_check_at']) {
            $body .= "Проверка: {$status['last_check_at']}";
        }

        Notification::make()
            ->title('Статус Telegram')
            ->body($body)
            ->info()
            ->duration(15000)
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testMeta')
                ->label('Тест Meta')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action('testConnection'),

            Action::make('testGemini')
                ->label('Тест AI')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->action('testGemini'),

            Action::make('testTelegram')
                ->label('Тест TG')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->action('testTelegram'),

            Action::make('setWebhook')
                ->label('Webhook TG')
                ->icon('heroicon-o-link')
                ->color('info')
                ->action('setTelegramWebhook')
                ->requiresConfirmation()
                ->modalHeading('Установить Telegram Webhook')
                ->modalDescription('Webhook будет установлен на адрес: '.url('/api/webhooks/telegram'))
                ->modalSubmitActionLabel('Установить'),

            Action::make('save')
                ->label('Сохранить')
                ->icon('heroicon-o-check')
                ->action('save'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
