<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Добавляем настройку Telegram bot token
        DB::table('settings')->insertOrIgnore([
            'key' => 'telegram_bot_token',
            'value' => '',
            'group' => 'telegram',
            'type' => 'string',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'telegram_bot_token')->delete();
    }
};
