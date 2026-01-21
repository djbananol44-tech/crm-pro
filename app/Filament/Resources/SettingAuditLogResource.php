<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingAuditLogResource\Pages;
use App\Models\SettingAuditLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SettingAuditLogResource extends Resource
{
    protected static ?string $model = SettingAuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Журнал изменений';

    protected static ?string $modelLabel = 'Запись журнала';

    protected static ?string $pluralModelLabel = 'Журнал изменений настроек';

    protected static ?string $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 101;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата/время')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('setting_key')
                    ->label('Настройка')
                    ->formatStateUsing(fn ($record) => $record->key_label)
                    ->badge()
                    ->color(fn ($record) => $record->is_secret ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('action')
                    ->label('Действие')
                    ->formatStateUsing(fn ($record) => $record->action_label)
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('change_description')
                    ->label('Описание')
                    ->wrap(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Пользователь')
                    ->default('Система')
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_secret')
                    ->label('🔒')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('setting_key')
                    ->label('Настройка')
                    ->options(fn () => SettingAuditLog::distinct('setting_key')
                        ->pluck('setting_key', 'setting_key')
                        ->mapWithKeys(fn ($key) => [
                            $key => (new SettingAuditLog(['setting_key' => $key]))->key_label,
                        ])
                        ->toArray()
                    ),

                Tables\Filters\SelectFilter::make('action')
                    ->label('Действие')
                    ->options([
                        'created' => 'Создано',
                        'updated' => 'Изменено',
                        'deleted' => 'Удалено',
                    ]),

                Tables\Filters\TernaryFilter::make('is_secret')
                    ->label('Секретные ключи')
                    ->boolean()
                    ->trueLabel('Только секретные')
                    ->falseLabel('Только обычные')
                    ->placeholder('Все'),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Пользователь')
                    ->relationship('user', 'name'),
            ])
            ->actions([
                // Только просмотр, редактирование запрещено
            ])
            ->bulkActions([
                // Массовые действия запрещены
            ])
            ->poll('30s'); // Автообновление каждые 30 сек
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettingAuditLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
