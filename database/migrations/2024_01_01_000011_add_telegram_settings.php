<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Настройки уже созданы в основной миграции settings
        // Эта миграция оставлена для совместимости
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'telegram_bot_token')->delete();
    }
};
