<?php

namespace App\Filament\Resources\SettingAuditLogResource\Pages;

use App\Filament\Resources\SettingAuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListSettingAuditLogs extends ListRecords
{
    protected static string $resource = SettingAuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Журнал изменений настроек';
    }

    public function getSubheading(): ?string
    {
        return 'История всех изменений конфигурации системы (значения секретов не логируются)';
    }
}
