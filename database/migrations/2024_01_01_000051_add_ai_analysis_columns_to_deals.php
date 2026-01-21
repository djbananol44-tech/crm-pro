<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            // Расширенные поля AI анализа
            if (!Schema::hasColumn('deals', 'ai_intent')) {
                $table->string('ai_intent')->nullable()->after('ai_score')
                    ->comment('Намерение клиента');
            }

            if (!Schema::hasColumn('deals', 'ai_objections')) {
                $table->json('ai_objections')->nullable()->after('ai_intent')
                    ->comment('Возражения клиента (массив)');
            }

            if (!Schema::hasColumn('deals', 'ai_next_action')) {
                $table->text('ai_next_action')->nullable()->after('ai_objections')
                    ->comment('Рекомендуемое следующее действие');
            }

            if (!Schema::hasColumn('deals', 'analysis_failed_at')) {
                $table->timestamp('analysis_failed_at')->nullable()->after('ai_summary_at')
                    ->comment('Время последней неудачной попытки анализа');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn([
                'ai_intent',
                'ai_objections',
                'ai_next_action',
                'analysis_failed_at',
            ]);
        });
    }
};
