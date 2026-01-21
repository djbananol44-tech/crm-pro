<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Запустить миграцию.
     */
    public function up(): void
    {
        // Настройки уже созданы в основной миграции settings
        // Эта миграция оставлена для совместимости
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', 'gemini_api_key')->delete();
        DB::table('settings')->where('key', 'ai_enabled')->delete();
    }
};
