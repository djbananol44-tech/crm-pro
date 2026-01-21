<?php

namespace App\Filament\Resources\DealResource\Pages;

use App\Filament\Resources\DealResource;
use App\Services\MetaApiService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;

class ViewDeal extends ViewRecord
{
    protected static string $resource = DealResource::class;

    protected static string $view = 'filament.resources.deal-resource.pages.view-deal';

    public array $messages = [];

    public bool $messagesLoaded = false;

    public ?string $messagesError = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->loadMessages();
    }

    protected function loadMessages(): void
    {
        try {
            $metaApi = app(MetaApiService::class);

            if (!$metaApi->isConfigured()) {
                $this->messagesError = 'Meta API не настроен. Укажите настройки в разделе "Настройки".';

                return;
            }

            if (!$this->record->conversation) {
                $this->messagesError = 'Нет связанной беседы.';

                return;
            }

            // Политика Meta: максимум 20 сообщений
            $this->messages = $metaApi->getMessages(
                $this->record->conversation->conversation_id,
                MetaApiService::MAX_MESSAGES_PER_CONVERSATION
            );

            $this->messagesLoaded = true;

            Log::info('ViewDeal: Загружены сообщения для админа', [
                'deal_id' => $this->record->id,
                'count' => count($this->messages),
            ]);

        } catch (\Exception $e) {
            $this->messagesError = 'Ошибка загрузки сообщений: '.$e->getMessage();
            Log::error('ViewDeal: Ошибка загрузки сообщений', [
                'deal_id' => $this->record->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function refreshMessages(): void
    {
        $this->messagesError = null;
        $this->messages = [];
        $this->messagesLoaded = false;
        $this->loadMessages();

        Notification::make()
            ->title('Сообщения обновлены')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refreshMessages')
                ->label('Обновить чат')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshMessages'),
            Actions\Action::make('openInManager')
                ->label('Открыть в интерфейсе менеджера')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(route('deals.show', $this->record))
                ->openUrlInNewTab(),
            Actions\EditAction::make(),
        ];
    }

    protected function getRelations(): array
    {
        return [
            DealResource\RelationManagers\ActivityLogsRelationManager::class,
        ];
    }
}
