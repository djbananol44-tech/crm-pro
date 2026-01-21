<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DealResource\Pages;
use App\Models\Deal;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DealResource extends Resource
{
    protected static ?string $model = Deal::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Все сделки';

    protected static ?string $modelLabel = 'Сделка';

    protected static ?string $pluralModelLabel = 'Сделки';

    protected static ?string $navigationGroup = 'Управление';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $user = auth()->user();
        $isAdmin = $user && $user->isAdmin();

        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')
                    ->schema([
                        Forms\Components\Select::make('contact_id')
                            ->label('Контакт')
                            ->relationship('contact', 'name', fn ($query) => $query->whereNotNull('name'))
                            ->searchable(['name', 'first_name', 'last_name', 'psid'])
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),
                        Forms\Components\Select::make('conversation_id')
                            ->label('Беседа')
                            ->relationship('conversation', 'conversation_id')
                            ->searchable()
                            ->preload()
                            ->disabled(fn ($record) => $record !== null),
                        Forms\Components\Select::make('manager_id')
                            ->label('Ответственный менеджер')
                            ->options(User::whereIn('role', ['manager', 'admin'])->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->disabled(function ($record) use ($isAdmin) {
                                if ($record && $record->manager_id !== null && !$isAdmin) {
                                    return true;
                                }

                                return false;
                            }),
                        Forms\Components\Select::make('status')
                            ->label('Статус')
                            ->options([
                                'New' => 'Новая',
                                'In Progress' => 'В работе',
                                'Closed' => 'Закрыта',
                            ])
                            ->required()
                            ->default('New'),
                    ])->columns(2),

                Forms\Components\Section::make('Детали')
                    ->schema([
                        Forms\Components\Textarea::make('comment')
                            ->label('Комментарий')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('reminder_at')
                            ->label('Напоминание')
                            ->native(false)
                            ->displayFormat('d.m.Y H:i'),
                        Forms\Components\Toggle::make('is_priority')
                            ->label('Приоритетная сделка')
                            ->helperText('Горячий вопрос от клиента'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('contact.name')
                    ->label('Клиент')
                    ->description(fn (Deal $record): string => $record->contact?->psid ?? '')
                    ->searchable(['contacts.name', 'contacts.first_name', 'contacts.last_name', 'contacts.psid'])
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Менеджер')
                    ->badge()
                    ->color(fn (Deal $record): string => $record->manager?->isOnline() ? 'success' : 'gray')
                    ->icon(fn (Deal $record): string => $record->manager?->isOnline() ? 'heroicon-o-signal' : 'heroicon-o-signal-slash')
                    ->default('Не назначен')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'New' => 'info',
                        'In Progress' => 'warning',
                        'Closed' => 'success',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'New' => 'Новая',
                        'In Progress' => 'В работе',
                        'Closed' => 'Закрыта',
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_priority')
                    ->label('🔥')
                    ->boolean()
                    ->trueIcon('heroicon-o-fire')
                    ->falseIcon('')
                    ->trueColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ai_score')
                    ->label('Score')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state > 80 => 'danger',
                        $state > 60 => 'warning',
                        $state > 0 => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state}" : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('manager_rating')
                    ->label('Оценка')
                    ->formatStateUsing(fn (?int $state): string => $state ? str_repeat('⭐', $state) : '—')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_viewed')
                    ->label('👁️')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->trueColor('success')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('conversation.platform')
                    ->label('Платформа')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'instagram' ? 'pink' : 'info')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'instagram' => 'Instagram',
                        'messenger' => 'Messenger',
                        default => $state ?? '—',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'New' => 'Новая',
                        'In Progress' => 'В работе',
                        'Closed' => 'Закрыта',
                    ]),
                Tables\Filters\SelectFilter::make('manager_id')
                    ->label('Менеджер')
                    ->options(User::whereIn('role', ['manager', 'admin'])->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_priority')
                    ->label('Приоритет')
                    ->trueLabel('Только приоритетные')
                    ->falseLabel('Без приоритета'),
                Tables\Filters\TernaryFilter::make('is_viewed')
                    ->label('Просмотр')
                    ->trueLabel('Просмотренные')
                    ->falseLabel('Непросмотренные'),
                Tables\Filters\Filter::make('has_rating')
                    ->label('С оценкой')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('manager_rating')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Открыть'),
                Tables\Actions\Action::make('openChat')
                    ->label('Чат')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (Deal $record): string => route('deals.show', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->poll('30s'); // Автообновление каждые 30 секунд
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Информация о клиенте')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Infolists\Components\TextEntry::make('contact.name')
                            ->label('Имя'),
                        Infolists\Components\TextEntry::make('contact.psid')
                            ->label('PSID')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('conversation.platform')
                            ->label('Платформа')
                            ->badge(),
                        Infolists\Components\TextEntry::make('conversation.link')
                            ->label('Ссылка на чат')
                            ->url(fn (Deal $record): ?string => $record->conversation?->link)
                            ->openUrlInNewTab(),
                    ])->columns(2),

                Infolists\Components\Section::make('Статус сделки')
                    ->icon('heroicon-o-briefcase')
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'New' => 'info',
                                'In Progress' => 'warning',
                                'Closed' => 'success',
                            }),
                        Infolists\Components\TextEntry::make('manager.name')
                            ->label('Менеджер')
                            ->default('Не назначен'),
                        Infolists\Components\IconEntry::make('is_priority')
                            ->label('Приоритет')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('ai_score')
                            ->label('AI Score')
                            ->badge()
                            ->color(fn (?int $state): string => $state > 80 ? 'danger' : ($state > 60 ? 'warning' : 'gray')),
                    ])->columns(4),

                Infolists\Components\Section::make('AI Анализ')
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        Infolists\Components\TextEntry::make('ai_summary')
                            ->label('Резюме')
                            ->columnSpanFull()
                            ->prose(),
                        Infolists\Components\TextEntry::make('manager_rating')
                            ->label('Оценка менеджера')
                            ->formatStateUsing(fn (?int $state): string => $state ? str_repeat('⭐', $state)." ({$state}/5)" : '—'),
                        Infolists\Components\TextEntry::make('manager_review')
                            ->label('Отзыв AI')
                            ->columnSpanFull(),
                    ])->collapsible(),

                Infolists\Components\Section::make('Комментарий')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->schema([
                        Infolists\Components\TextEntry::make('comment')
                            ->label('')
                            ->columnSpanFull()
                            ->default('Нет комментария'),
                    ])->collapsible(),

                Infolists\Components\Section::make('Даты')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Создана')
                            ->dateTime('d.m.Y H:i'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Обновлена')
                            ->since(),
                        Infolists\Components\TextEntry::make('reminder_at')
                            ->label('Напоминание')
                            ->dateTime('d.m.Y H:i'),
                        Infolists\Components\TextEntry::make('rated_at')
                            ->label('Оценена')
                            ->dateTime('d.m.Y H:i'),
                    ])->columns(4)->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DealResource\RelationManagers\ActivityLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeals::route('/'),
            'create' => Pages\CreateDeal::route('/create'),
            'view' => Pages\ViewDeal::route('/{record}'),
            'edit' => Pages\EditDeal::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereIn('status', ['New', 'In Progress'])->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::where('status', 'New')->count();

        return $count > 0 ? 'danger' : 'primary';
    }
}
