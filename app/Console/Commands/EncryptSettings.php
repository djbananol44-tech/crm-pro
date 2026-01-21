<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Команда для шифрования существующих секретных настроек.
 *
 * Выполняется один раз после миграции для защиты уже сохранённых данных.
 */
class EncryptSettings extends Command
{
    protected $signature = 'settings:encrypt 
                            {--dry-run : Показать что будет зашифровано без изменений}
                            {--force : Перешифровать даже уже зашифрованные}';

    protected $description = 'Зашифровать секретные настройки в базе данных';

    public function handle(): int
    {
        $this->info('🔐 Шифрование секретных настроек');
        $this->newLine();

        $secretKeys = Setting::getSecretKeys();
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('⚠️  Режим DRY RUN — изменения не будут применены');
            $this->newLine();
        }

        $encrypted = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($secretKeys as $key) {
            $setting = Setting::where('key', $key)->first();

            if (!$setting) {
                $this->line("  ⏭️  {$key}: не найден");
                $skipped++;

                continue;
            }

            if (empty($setting->value)) {
                $this->line("  ⏭️  {$key}: пустое значение");
                $skipped++;

                continue;
            }

            // Уже зашифровано?
            if ($setting->is_encrypted && !$force) {
                $this->line("  ✅ {$key}: уже зашифровано");
                $skipped++;

                continue;
            }

            // Проверяем, не зашифровано ли уже (попытка расшифровать)
            if (!$setting->is_encrypted) {
                try {
                    // Пытаемся расшифровать — если получилось, значит уже зашифровано
                    Crypt::decryptString($setting->value);
                    $this->warn("  ⚠️  {$key}: похоже уже зашифровано (без флага)");

                    if (!$dryRun) {
                        $setting->update(['is_encrypted' => true]);
                        $this->line('     → Флаг is_encrypted установлен');
                    }

                    continue;
                } catch (\Exception $e) {
                    // Хорошо — значит не зашифровано, продолжаем
                }
            }

            // Шифруем
            $this->info("  🔒 {$key}: шифрование...");

            if ($dryRun) {
                $this->line('     → будет зашифровано');
                $encrypted++;

                continue;
            }

            try {
                $originalValue = $setting->is_encrypted
                    ? Crypt::decryptString($setting->value)
                    : $setting->value;

                $encryptedValue = Crypt::encryptString($originalValue);

                DB::table('settings')
                    ->where('key', $key)
                    ->update([
                        'value' => $encryptedValue,
                        'is_encrypted' => true,
                        'updated_at' => now(),
                    ]);

                // Очищаем кэш
                cache()->forget("setting:{$key}");

                $this->info('     → успешно зашифровано');
                $encrypted++;

            } catch (\Exception $e) {
                $this->error("     → ошибка: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->table(['Результат', 'Количество'], [
            ['Зашифровано', $encrypted],
            ['Пропущено', $skipped],
            ['Ошибок', $errors],
        ]);

        if ($errors > 0) {
            return Command::FAILURE;
        }

        if ($encrypted > 0 && !$dryRun) {
            $this->newLine();
            $this->info('✅ Шифрование завершено!');
            $this->warn('⚠️  Убедитесь, что APP_KEY сохранён в надёжном месте.');
            $this->line('   Без него невозможно расшифровать данные!');
        }

        return Command::SUCCESS;
    }
}
