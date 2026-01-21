<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use App\Imports\ContactsImport;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('Импорт CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label('Файл CSV')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', '.csv'])
                        ->required()
                        ->helperText('Файл должен содержать колонки: psid, first_name, last_name'),
                ])
                ->action(function (array $data): void {
                    try {
                        $path = storage_path('app/public/'.$data['file']);

                        $import = new ContactsImport;
                        Excel::import($import, $path);

                        $created = $import->getCreatedCount();
                        $updated = $import->getUpdatedCount();

                        Log::info('ContactsImport: Импорт завершён', [
                            'created' => $created,
                            'updated' => $updated,
                        ]);

                        Notification::make()
                            ->title('Импорт успешно завершён')
                            ->body("Создано: {$created}, Обновлено: {$updated}")
                            ->success()
                            ->send();

                        // Удаляем временный файл
                        if (file_exists($path)) {
                            unlink($path);
                        }

                    } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                        $failures = $e->failures();
                        $errors = [];

                        foreach ($failures as $failure) {
                            $errors[] = "Строка {$failure->row()}: ".implode(', ', $failure->errors());
                        }

                        Log::error('ContactsImport: Ошибки валидации', [
                            'errors' => $errors,
                        ]);

                        Notification::make()
                            ->title('Ошибки валидации при импорте')
                            ->body(implode("\n", array_slice($errors, 0, 5)))
                            ->danger()
                            ->persistent()
                            ->send();

                    } catch (\Exception $e) {
                        Log::error('ContactsImport: Ошибка импорта', [
                            'error' => $e->getMessage(),
                        ]);

                        Notification::make()
                            ->title('Ошибка импорта')
                            ->body('Произошла ошибка при импорте файла: '.$e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('downloadTemplate')
                ->label('Скачать шаблон')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $headers = [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => 'attachment; filename="contacts_template.csv"',
                    ];

                    $content = "psid,first_name,last_name\n";
                    $content .= "123456789,Иван,Петров\n";
                    $content .= "987654321,Мария,Сидорова\n";

                    return response($content, 200, $headers);
                }),

            Actions\CreateAction::make()
                ->label('Создать контакт'),
        ];
    }
}
