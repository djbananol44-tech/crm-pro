<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationLabel = 'Контакты';
    
    protected static ?string $modelLabel = 'Контакт';
    
    protected static ?string $pluralModelLabel = 'Контакты';
    
    protected static ?string $navigationGroup = 'Данные';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('psid')
                    ->label('PSID')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Page Scoped ID из Meta API'),
                Forms\Components\TextInput::make('first_name')
                    ->label('Имя')
                    ->maxLength(255),
                Forms\Components\TextInput::make('last_name')
                    ->label('Фамилия')
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->label('Полное имя')
                    ->maxLength(255)
                    ->helperText('Заполняется автоматически из Meta API'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('psid')
                    ->label('PSID')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('PSID скопирован'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Полное имя')
                    ->searchable()
                    ->sortable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Имя')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Фамилия')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('deals_count')
                    ->label('Сделок')
                    ->counts('deals')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_deals')
                    ->label('С активными сделками')
                    ->query(fn ($query) => $query->has('deals')),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }
}
