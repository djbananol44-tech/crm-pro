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
        // Добавляем настройку для Gemini API Key
        DB::table('settings')->insert([
            'key' => 'gemini_api_key',
            'value' => null,
            'group' => 'ai',
            'type' => 'string',
            'description' => 'API ключ для Google Gemini AI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Добавляем настройку для включения/отключения AI
        DB::table('settings')->insert([
            'key' => 'ai_enabled',
            'value' => 'false',
            'group' => 'ai',
            'type' => 'boolean',
            'description' => 'Включить интеграцию с AI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
