<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Запустить миграцию.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Ключ настройки');
            $table->text('value')->nullable()->comment('Значение настройки');
            $table->string('group')->default('general')->comment('Группа настроек');
            $table->string('type')->default('string')->comment('Тип значения: string, boolean, integer, json');
            $table->text('description')->nullable()->comment('Описание настройки');
            $table->timestamps();
        });

        // Создаём начальные настройки для Meta API
        DB::table('settings')->insert([
            [
                'key' => 'meta_page_id',
                'value' => env('META_PAGE_ID', ''),
                'group' => 'meta',
                'type' => 'string',
                'description' => 'ID страницы Facebook',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'meta_access_token',
                'value' => env('META_ACCESS_TOKEN', ''),
                'group' => 'meta',
                'type' => 'string',
                'description' => 'Токен доступа страницы Facebook',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'meta_webhook_verify_token',
                'value' => env('META_WEBHOOK_VERIFY_TOKEN', ''),
                'group' => 'meta',
                'type' => 'string',
                'description' => 'Токен верификации Webhook',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'meta_app_id',
                'value' => env('META_APP_ID', ''),
                'group' => 'meta',
                'type' => 'string',
                'description' => 'ID приложения Meta',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'meta_app_secret',
                'value' => env('META_APP_SECRET', ''),
                'group' => 'meta',
                'type' => 'string',
                'description' => 'Секретный ключ приложения Meta',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'gemini_api_key',
                'value' => env('GEMINI_API_KEY', ''),
                'group' => 'ai',
                'type' => 'string',
                'description' => 'API ключ Gemini',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ai_enabled',
                'value' => 'false',
                'group' => 'ai',
                'type' => 'boolean',
                'description' => 'Включить AI-ассистента',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'telegram_bot_token',
                'value' => env('TELEGRAM_BOT_TOKEN', ''),
                'group' => 'telegram',
                'type' => 'string',
                'description' => 'Токен Telegram бота',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
