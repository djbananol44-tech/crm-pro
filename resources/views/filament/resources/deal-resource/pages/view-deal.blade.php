<x-filament-panels::page>
    {{-- Информация о сделке --}}
    {{ $this->infolist }}

    {{-- Секция чата --}}
    <x-filament::section
        icon="heroicon-o-chat-bubble-left-right"
        heading="Переписка с клиентом"
        description="История сообщений из {{ $record->conversation?->platform ?? 'Meta' }}"
        collapsible
    >
        @if($messagesError)
            <div class="rounded-lg bg-danger-50 dark:bg-danger-950 p-4 text-danger-600 dark:text-danger-400">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                    <span>{{ $messagesError }}</span>
                </div>
            </div>
        @elseif(!$messagesLoaded)
            <div class="flex items-center justify-center py-8">
                <x-filament::loading-indicator class="w-8 h-8" />
                <span class="ml-3 text-sm text-gray-500">Загрузка сообщений...</span>
            </div>
        @elseif(empty($messages))
            <div class="text-center py-8 text-gray-500">
                <x-heroicon-o-chat-bubble-left-ellipsis class="w-12 h-12 mx-auto mb-3 opacity-50" />
                <p>Нет сообщений</p>
            </div>
        @else
            <div class="space-y-3 max-h-[600px] overflow-y-auto p-2">
                @foreach(array_reverse($messages) as $message)
                    @php
                        $isPage = ($message['from']['id'] ?? '') === app(\App\Services\MetaApiService::class)->getPageId();
                        $senderName = $message['from']['name'] ?? $message['from']['id'] ?? 'Неизвестно';
                        $messageText = $message['message'] ?? '';
                        $createdTime = isset($message['created_time']) 
                            ? \Carbon\Carbon::parse($message['created_time'])->format('d.m.Y H:i') 
                            : '';
                    @endphp
                    <div class="flex {{ $isPage ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%] rounded-2xl px-4 py-2 {{ $isPage 
                            ? 'bg-primary-500 text-white rounded-br-sm' 
                            : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-bl-sm' 
                        }}">
                            <div class="text-xs {{ $isPage ? 'text-primary-100' : 'text-gray-500 dark:text-gray-400' }} mb-1">
                                {{ $senderName }} • {{ $createdTime }}
                            </div>
                            <div class="text-sm whitespace-pre-wrap break-words">
                                {{ $messageText }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 pt-4 border-t dark:border-gray-700 flex items-center justify-between text-sm text-gray-500">
                <span>Показано {{ count($messages) }} сообщений</span>
                <x-filament::button 
                    wire:click="refreshMessages" 
                    size="sm"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                >
                    Обновить
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>

    {{-- Связанные данные (история действий) --}}
    <x-filament-panels::resources.relation-managers
        :active-manager="$activeRelationManager"
        :managers="$this->getRelationManagers()"
        :owner-record="$record"
        :page-class="static::class"
    />
</x-filament-panels::page>
