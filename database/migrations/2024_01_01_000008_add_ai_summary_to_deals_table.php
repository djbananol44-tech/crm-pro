<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запустить миграцию.
     */
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->text('ai_summary')->nullable()->after('comment')->comment('AI-анализ переписки');
            $table->timestamp('ai_summary_at')->nullable()->after('ai_summary')->comment('Дата последнего AI-анализа');
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['ai_summary', 'ai_summary_at']);
        });
    }
};
