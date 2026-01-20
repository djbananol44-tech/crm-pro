<?php

namespace App\Filament\Widgets;

use App\Models\ActivityLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ManagerActivityWidget extends BaseWidget
{
    protected static ?string $heading = '🔴 Последние действия менеджеров';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = '15s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ActivityLog::query()
                    ->with(['user', 'deal.contact'])
                    ->whereNotNull('user_id')
                    ->whereHas('user', fn ($q) => $q->where('role', 'manager'))
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Время')
                    ->dateTime('H:i:s')
                    ->description(fn (ActivityLog $record): string => $record->created_at->format('d.m.Y'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Менеджер')
                    ->badge()
                    ->color(fn (ActivityLog $record): string => 
                        $record->user?->isOnline() ? 'success' : 'gray'
                    )
                    ->icon(fn (ActivityLog $record): string => 
                        $record->user?->isOnline() ? 'heroicon-o-signal' : 'heroicon-o-signal-slash'
                    ),

                Tables\Columns\TextColumn::make('action')
                    ->label('Действие')
                    ->formatStateUsing(fn (ActivityLog $record): string => 
                        $record->icon . ' ' . match ($record->action) {
                            'viewed' => 'Просмотр',
                            'status_changed' => 'Смена статуса',
                            'manager_assigned' => 'Назначение',
                            'comment_added' => 'Комментарий',
                            'reminder_set' => 'Напоминание',
                            'login' => 'Вход',
                            default => $record->action,
                        }
                    ),

                Tables\Columns\TextColumn::make('deal.contact.name')
                    ->label('Клиент')
                    ->description(fn (ActivityLog $record): string => 
                        $record->deal ? "Сделка #{$record->deal->id}" : ''
                    )
                    ->url(fn (ActivityLog $record): ?string => 
                        $record->deal 
                            ? route('filament.admin.resources.deals.view', $record->deal) 
                            : null
                    ),

                Tables\Columns\TextColumn::make('description')
                    ->label('Подробности')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Менеджер')
                    ->relationship('user', 'name', fn ($query) => $query->where('role', 'manager')),
                Tables\Filters\SelectFilter::make('action')
                    ->label('Действие')
                    ->options([
                        'viewed' => 'Просмотр',
                        'status_changed' => 'Смена статуса',
                        'comment_added' => 'Комментарий',
                        'manager_assigned' => 'Назначение',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('viewDeal')
                    ->label('Сделка')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ActivityLog $record): ?string => 
                        $record->deal 
                            ? route('filament.admin.resources.deals.view', $record->deal) 
                            : null
                    )
                    ->visible(fn (ActivityLog $record): bool => $record->deal !== null),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
