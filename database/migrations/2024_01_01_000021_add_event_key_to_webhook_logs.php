<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Добавляет уникальный ключ события для идемпотентной обработки webhook.
 *
 * Event key формируется из:
 * - message.mid (Message ID от Meta) — предпочтительно
 * - или sha256(entry_id + sender_id + timestamp + message_hash)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            // Уникальный ключ события (sha256 = 64 символа)
            $table->string('event_key', 64)
                ->nullable()
                ->after('event_type')
                ->comment('Уникальный ключ для идемпотентности');

            // Unique index для быстрой проверки дублей
            $table->unique(['source', 'event_key'], 'webhook_logs_source_event_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropUnique('webhook_logs_source_event_key_unique');
            $table->dropColumn('event_key');
        });
    }
};
