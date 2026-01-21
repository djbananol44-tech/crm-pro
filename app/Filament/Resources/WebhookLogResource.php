<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WebhookLogResource\Pages;
use App\Models\WebhookLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class WebhookLogResource extends Resource
{
    protected static ?string $model = WebhookLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationLabel = 'Логи вебхуков';
    protected static ?string $modelLabel = 'Лог вебхука';
    protected static ?string $pluralModelLabel = 'Логи вебхуков';
    protected static ?string $navigationGroup = 'Система';
    protected static ?int $navigationSort = 50;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('source')
                    ->label('Источник')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'meta' => 'info',
                        'telegram' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'meta' => '📘 Meta',
                        'telegram' => '📱 Telegram',
                        default => $state,
                    }),
                    
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Тип события')
                    ->limit(30)
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('response_code')
                    ->label('Код')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 200 && $state < 300 => 'success',
                        $state >= 400 => 'danger',
                        default => 'warning',
                    }),
                    
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\IconColumn::make('error_message')
                    ->label('Ошибка')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->getStateUsing(fn ($record) => !empty($record->error_message)),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Получен')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Обработан')
                    ->dateTime('H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->label('Источник')
                    ->options([
                        'meta' => 'Meta (Facebook)',
                        'telegram' => 'Telegram',
                    ]),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'success' => 'Успешно',
                        'error' => 'Ошибка',
                        'pending' => 'В обработке',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'success' => $query->where('response_code', '>=', 200)->where('response_code', '<', 300),
                            'error' => $query->where('response_code', '>=', 400),
                            'pending' => $query->whereNull('response_code'),
                            default => $query,
                        };
                    }),
                    
                Tables\Filters\Filter::make('has_error')
                    ->label('Только с ошибками')
                    ->query(fn ($query) => $query->whereNotNull('error_message')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->poll('10s');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Информация')
                    ->schema([
                        Components\TextEntry::make('source')
                            ->label('Источник')
                            ->badge(),
                        Components\TextEntry::make('event_type')
                            ->label('Тип события'),
                        Components\TextEntry::make('ip_address')
                            ->label('IP адрес'),
                        Components\TextEntry::make('response_code')
                            ->label('HTTP код')
                            ->badge()
                            ->color(fn (?int $state): string => match (true) {
                                $state === null => 'gray',
                                $state >= 200 && $state < 300 => 'success',
                                $state >= 400 => 'danger',
                                default => 'warning',
                            }),
                        Components\TextEntry::make('created_at')
                            ->label('Получен')
                            ->dateTime('d.m.Y H:i:s'),
                        Components\TextEntry::make('processed_at')
                            ->label('Обработан')
                            ->dateTime('d.m.Y H:i:s'),
                    ])
                    ->columns(3),
                    
                Components\Section::make('Входящие данные (Payload)')
                    ->schema([
                        Components\TextEntry::make('payload')
                            ->label('')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                    
                Components\Section::make('Ошибка')
                    ->schema([
                        Components\TextEntry::make('error_message')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => !empty($record->error_message))
                    ->collapsed(false),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebhookLogs::route('/'),
            'view' => Pages\ViewWebhookLog::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = static::getModel()::where('created_at', '>=', now()->subHour())
                ->whereNotNull('error_message')
                ->count();
                
            return $count > 0 ? (string) $count : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
