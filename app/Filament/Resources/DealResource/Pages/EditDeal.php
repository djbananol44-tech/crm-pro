<?php

namespace App\Filament\Resources\DealResource\Pages;

use App\Filament\Resources\DealResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeal extends EditRecord
{
    protected static string $resource = DealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        $record = $this->record;

        // Если менеджер уже назначен и пользователь не админ - сохраняем старое значение
        if ($record->manager_id !== null && !$user->isAdmin()) {
            $data['manager_id'] = $record->manager_id;
        }

        return $data;
    }
}
