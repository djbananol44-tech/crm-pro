<?php

namespace App\Filament\Resources\WebhookLogResource\Pages;

use App\Filament\Resources\WebhookLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWebhookLogs extends ListRecords
{
    protected static string $resource = WebhookLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('clear_old')
                ->label('Очистить старые')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Очистить старые логи?')
                ->modalDescription('Будут удалены все логи старше 7 дней')
                ->action(function () {
                    $deleted = \App\Models\WebhookLog::where('created_at', '<', now()->subDays(7))->delete();

                    \Filament\Notifications\Notification::make()
                        ->title("Удалено {$deleted} записей")
                        ->success()
                        ->send();
                }),
        ];
    }
}
