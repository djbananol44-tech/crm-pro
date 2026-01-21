<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Добавляем поля для корректного построения ссылки на Meta Business Suite
     * и хранения labels/tags из Meta API.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Page ID для построения корректной ссылки
            $table->string('page_id')->nullable()->after('platform')
                ->comment('Facebook Page ID для ссылки Meta Business Suite');

            // Labels/Tags из Meta API (JSON array)
            $table->json('labels')->nullable()->after('page_id')
                ->comment('Лейблы/теги из Meta Business Suite');

            // Индекс для быстрого поиска по page_id
            $table->index('page_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['page_id']);
            $table->dropColumn(['page_id', 'labels']);
        });
    }
};
