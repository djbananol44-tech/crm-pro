<?php

namespace App\Filament\Widgets;

use App\Models\Deal;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentDealsWidget extends BaseWidget
{
    protected static ?string $heading = 'Последние сделки';
    
    protected static ?int $sort = 4;
    
    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Deal::with(['contact', 'manager', 'conversation'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->color('gray')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('contact.name')
                    ->label('Клиент')
                    ->default('—')
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn (Deal $record): string => $record->contact?->psid ?? ''),
                    
                Tables\Columns\TextColumn::make('conversation.platform')
                    ->label('Источник')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'instagram' => 'danger',
                        'messenger' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'instagram' => 'Instagram',
                        'messenger' => 'Messenger',
                        default => $state,
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'instagram' => 'heroicon-o-camera',
                        'messenger' => 'heroicon-o-chat-bubble-left-right',
                        default => 'heroicon-o-globe-alt',
                    }),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'New' => 'primary',
                        'In Progress' => 'warning',
                        'Closed' => 'success',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'New' => 'heroicon-o-sparkles',
                        'In Progress' => 'heroicon-o-clock',
                        'Closed' => 'heroicon-o-check-circle',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'New' => 'Новая',
                        'In Progress' => 'В работе',
                        'Closed' => 'Завершена',
                    }),
                    
                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Менеджер')
                    ->default('—')
                    ->color('gray')
                    ->icon('heroicon-o-user'),
                    
                Tables\Columns\TextColumn::make('reminder_at')
                    ->label('Напоминание')
                    ->dateTime('d.m H:i')
                    ->color(fn (Deal $record): string => 
                        $record->reminder_at && $record->reminder_at < now() ? 'danger' : 'gray'
                    )
                    ->icon(fn (Deal $record): string => 
                        $record->reminder_at && $record->reminder_at < now() 
                            ? 'heroicon-o-exclamation-triangle' 
                            : 'heroicon-o-bell'
                    )
                    ->placeholder('—'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создана')
                    ->since()
                    ->sortable()
                    ->color('gray')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Открыть')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn (Deal $record): string => route('filament.admin.resources.deals.view', $record))
                    ->openUrlInNewTab(),
            ])
            ->striped()
            ->paginated(false)
            ->defaultSort('created_at', 'desc');
    }
}
