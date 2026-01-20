<?php

namespace App\Filament\Resources\DealResource\RelationManagers;

use App\Models\ActivityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'История действий';

    protected static ?string $modelLabel = 'Действие';

    protected static ?string $pluralModelLabel = 'Действия';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('action')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Время')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Кто')
                    ->default('Система')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('action')
                    ->label('Действие')
                    ->badge()
                    ->color(fn (ActivityLog $record): string => $record->color)
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => '🆕 Создано',
                        'viewed' => '👁️ Просмотр',
                        'status_changed' => '🔄 Статус',
                        'manager_assigned' => '👨‍💼 Назначение',
                        'comment_added' => '💬 Комментарий',
                        'reminder_set' => '⏰ Напоминание',
                        'ai_analyzed' => '🤖 AI-анализ',
                        'priority_set' => '🔥 Приоритет',
                        'rated' => '⭐ Оценка',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Описание')
                    ->wrap()
                    ->limit(80),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Тип действия')
                    ->options([
                        'viewed' => 'Просмотр',
                        'status_changed' => 'Смена статуса',
                        'manager_assigned' => 'Назначение',
                        'comment_added' => 'Комментарий',
                    ]),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Пользователь')
                    ->relationship('user', 'name'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->poll('15s');
    }
}
