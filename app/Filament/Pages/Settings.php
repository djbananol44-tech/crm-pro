<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class Settings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Настройки';
    protected static ?string $title = 'Настройки системы';
    protected static ?string $navigationGroup = 'Настройки';
    protected static ?int $navigationSort = 100;
    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // Meta API
            'meta_page_id' => Setting::get('meta_page_id', ''),
            'meta_access_token' => Setting::get('meta_access_token', ''),
            'meta_webhook_verify_token' => Setting::get('meta_webhook_verify_token', ''),
            'meta_app_id' => Setting::get('meta_app_id', ''),
            'meta_app_secret' => Setting::get('meta_app_secret', ''),
            // AI Integration
            'gemini_api_key' => Setting::get('gemini_api_key', ''),
            'ai_enabled' => Setting::get('ai_enabled', false),
            // Telegram
            'telegram_bot_token' => Setting::get('telegram_bot_token', ''),
        ]);
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
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('meta_page_id')
                                    ->label('Page ID')
                                    ->helperText('ID страницы Facebook')
                                    ->placeholder('123456789012345')
                                    ->required()
                                    ->prefixIcon('heroicon-o-identification'),

                                Forms\Components\TextInput::make('meta_webhook_verify_token')
                                    ->label('Webhook Verify Token')
                                    ->helperText('Токен для верификации webhook')
                                    ->placeholder('my_secure_token_123')
                                    ->required()
                                    ->prefixIcon('heroicon-o-shield-check'),
                            ]),

                        Forms\Components\Textarea::make('meta_access_token')
                            ->label('Access Token')
                            ->helperText('Долгосрочный токен доступа страницы')
                            ->placeholder('EAAxxxxxxx...')
                            ->rows(2)
                            ->required(),

                        Forms\Components\Fieldset::make('Дополнительно')
                            ->schema([
                                Forms\Components\TextInput::make('meta_app_id')
                                    ->label('App ID')
                                    ->placeholder('123456789012345')
                                    ->prefixIcon('heroicon-o-cube'),

                                Forms\Components\TextInput::make('meta_app_secret')
                                    ->label('App Secret')
                                    ->password()
                                    ->revealable()
                                    ->prefixIcon('heroicon-o-key'),
                            ])
                            ->columns(2),
                    ]),

                Forms\Components\Section::make('Telegram уведомления')
                    ->description('Мгновенные уведомления менеджерам в Telegram')
                    ->icon('heroicon-o-paper-airplane')
                    ->iconColor('info')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('telegram_bot_token')
                            ->label('Bot Token')
                            ->helperText('Токен бота от @BotFather (формат: 123456:ABC-DEF)')
                            ->placeholder('123456789:ABCdefGHIjklMNOpqrsTUVwxyz')
                            ->password()
                            ->revealable()
                            ->prefixIcon('heroicon-o-key'),

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
                            ->helperText('Получите ключ на ai.google.dev')
                            ->placeholder('AIzaSy...')
                            ->password()
                            ->revealable()
                            ->prefixIcon('heroicon-o-key'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            Setting::set('meta_page_id', $data['meta_page_id']);
            Setting::set('meta_access_token', $data['meta_access_token']);
            Setting::set('meta_webhook_verify_token', $data['meta_webhook_verify_token']);
            Setting::set('meta_app_id', $data['meta_app_id'] ?? '');
            Setting::set('meta_app_secret', $data['meta_app_secret'] ?? '');
            Setting::set('gemini_api_key', $data['gemini_api_key'] ?? '');
            Setting::set('ai_enabled', $data['ai_enabled'] ? 'true' : 'false');
            Setting::set('telegram_bot_token', $data['telegram_bot_token'] ?? '');

            Setting::clearCache();

            Notification::make()
                ->title('Настройки сохранены')
                ->success()
                ->send();

            if (!empty($data['gemini_api_key']) && $data['ai_enabled']) {
                Notification::make()
                    ->title('AI-ассистент активирован')
                    ->icon('heroicon-o-sparkles')
                    ->iconColor('success')
                    ->send();
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Ошибка сохранения')
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
                ->body('Получено бесед: ' . count($conversations))
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
        $apiKey = $this->data['gemini_api_key'] ?? Setting::get('gemini_api_key');

        if (empty($apiKey)) {
            Notification::make()
                ->title('API ключ не указан')
                ->warning()
                ->send();
            return;
        }

        try {
            $response = Http::timeout(10)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$apiKey}",
                ['contents' => [['parts' => [['text' => 'Ответь: OK']]]]]
            );

            if ($response->successful()) {
                Notification::make()
                    ->title('Gemini работает!')
                    ->success()
                    ->send();
            } else {
                throw new \Exception($response->json('error.message') ?? 'Ошибка');
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Ошибка Gemini')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function testTelegram(): void
    {
        $token = $this->data['telegram_bot_token'] ?? Setting::get('telegram_bot_token');

        if (empty($token)) {
            Notification::make()
                ->title('Токен бота не указан')
                ->warning()
                ->send();
            return;
        }

        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");

            if ($response->successful() && $response->json('ok')) {
                $botName = $response->json('result.username');
                Notification::make()
                    ->title('Telegram бот подключен!')
                    ->body("@{$botName}")
                    ->success()
                    ->send();
            } else {
                throw new \Exception($response->json('description') ?? 'Ошибка');
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Ошибка Telegram')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function setTelegramWebhook(): void
    {
        $token = $this->data['telegram_bot_token'] ?? Setting::get('telegram_bot_token');

        if (empty($token)) {
            Notification::make()
                ->title('Токен бота не указан')
                ->warning()
                ->send();
            return;
        }

        try {
            $webhookUrl = url('/api/webhooks/telegram');

            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $webhookUrl,
                'allowed_updates' => ['message', 'callback_query'],
            ]);

            if ($response->successful() && $response->json('ok')) {
                Notification::make()
                    ->title('Webhook установлен!')
                    ->body($webhookUrl)
                    ->success()
                    ->send();
            } else {
                throw new \Exception($response->json('description') ?? 'Ошибка');
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Ошибка установки webhook')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
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
                ->modalDescription('Webhook будет установлен на адрес: ' . url('/api/webhooks/telegram'))
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
