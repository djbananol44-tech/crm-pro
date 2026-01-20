<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\ActivityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Пользователи';
    protected static ?string $modelLabel = 'Пользователь';
    protected static ?string $pluralModelLabel = 'Пользователи';
    protected static ?string $navigationGroup = 'Управление';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основные данные')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('role')
                            ->label('Роль')
                            ->options([
                                'admin' => 'Администратор',
                                'manager' => 'Менеджер',
                            ])
                            ->required()
                            ->default('manager'),
                        Forms\Components\TextInput::make('password')
                            ->label('Пароль')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Telegram уведомления')
                    ->description('Для получения мгновенных уведомлений о новых сообщениях')
                    ->schema([
                        Forms\Components\TextInput::make('telegram_chat_id')
                            ->label('Telegram Chat ID')
                            ->helperText('Напишите /start вашему боту и получите chat_id')
                            ->placeholder('123456789')
                            ->numeric()
                            ->prefixIcon('heroicon-o-paper-airplane'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Роль')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'manager' => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => 'Администратор',
                        'manager' => 'Менеджер',
                    }),

                // Статус присутствия
                Tables\Columns\TextColumn::make('presence')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (User $record): string => $record->getPresenceColor())
                    ->icon(fn (User $record): string => $record->isOnline() ? 'heroicon-o-signal' : 'heroicon-o-signal-slash')
                    ->getStateUsing(fn (User $record): string => $record->isOnline() ? 'В сети' : 'Оффлайн')
                    ->description(fn (User $record): string => $record->getPresenceStatus()),

                // Активные сделки
                Tables\Columns\TextColumn::make('deals_count')
                    ->label('Сделок')
                    ->counts('deals')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                // Средний рейтинг
                Tables\Columns\TextColumn::make('average_rating')
                    ->label('Рейтинг')
                    ->getStateUsing(fn (User $record): string => 
                        $record->getAverageRating() 
                            ? str_repeat('⭐', (int) $record->getAverageRating()) . " ({$record->getAverageRating()})" 
                            : '—'
                    ),

                Tables\Columns\IconColumn::make('telegram_chat_id')
                    ->label('TG')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn ($record) => !empty($record->telegram_chat_id)),

                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Последняя активность')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Роль')
                    ->options([
                        'admin' => 'Администратор',
                        'manager' => 'Менеджер',
                    ]),
                Tables\Filters\TernaryFilter::make('online')
                    ->label('Статус')
                    ->trueLabel('В сети')
                    ->falseLabel('Оффлайн')
                    ->queries(
                        true: fn (Builder $query) => $query->where('last_activity_at', '>=', now()->subMinutes(5)),
                        false: fn (Builder $query) => $query->where(function ($q) {
                            $q->whereNull('last_activity_at')
                              ->orWhere('last_activity_at', '<', now()->subMinutes(5));
                        }),
                    ),
                Tables\Filters\TernaryFilter::make('has_telegram')
                    ->label('Telegram')
                    ->trueLabel('Подключен')
                    ->falseLabel('Не подключен')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('telegram_chat_id'),
                        false: fn (Builder $query) => $query->whereNull('telegram_chat_id'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_activity_at', 'desc')
            ->poll('30s');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Информация о пользователе')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Имя'),
                        Infolists\Components\TextEntry::make('email')
                            ->label('Email')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('role')
                            ->label('Роль')
                            ->badge(),
                        Infolists\Components\IconEntry::make('telegram_chat_id')
                            ->label('Telegram')
                            ->boolean()
                            ->getStateUsing(fn (User $record): bool => !empty($record->telegram_chat_id)),
                    ])->columns(4),

                Infolists\Components\Section::make('Статистика за сегодня')
                    ->schema([
                        Infolists\Components\TextEntry::make('today_views')
                            ->label('Просмотров карточек')
                            ->getStateUsing(fn (User $record): int => $record->getTodayStats()['views']),
                        Infolists\Components\TextEntry::make('today_status_changes')
                            ->label('Смен статусов')
                            ->getStateUsing(fn (User $record): int => $record->getTodayStats()['status_changes']),
                        Infolists\Components\TextEntry::make('today_closed')
                            ->label('Закрытых сделок')
                            ->getStateUsing(fn (User $record): int => $record->getTodayStats()['closed_deals']),
                        Infolists\Components\TextEntry::make('average_rating')
                            ->label('Средний рейтинг')
                            ->getStateUsing(fn (User $record): string => 
                                $record->getAverageRating() ? "{$record->getAverageRating()}/5 ⭐" : '—'
                            ),
                    ])->columns(4),

                Infolists\Components\Section::make('Активность')
                    ->schema([
                        Infolists\Components\TextEntry::make('presence')
                            ->label('Статус')
                            ->badge()
                            ->color(fn (User $record): string => $record->getPresenceColor())
                            ->getStateUsing(fn (User $record): string => $record->getPresenceStatus()),
                        Infolists\Components\TextEntry::make('last_activity_at')
                            ->label('Последняя активность')
                            ->since(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Зарегистрирован')
                            ->dateTime('d.m.Y H:i'),
                    ])->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        // Показываем количество менеджеров онлайн
        $online = User::where('role', 'manager')
            ->where('last_activity_at', '>=', now()->subMinutes(5))
            ->count();

        return $online > 0 ? "🟢 {$online}" : null;
    }
}
