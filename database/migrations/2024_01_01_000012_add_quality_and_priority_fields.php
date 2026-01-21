<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Добавляем поля для оценки качества и приоритета в deals
        Schema::table('deals', function (Blueprint $table) {
            // Приоритетная сделка (горячие вопросы)
            $table->boolean('is_priority')->default(false)->after('is_viewed');

            // AI оценка качества работы менеджера (1-5)
            $table->tinyInteger('manager_rating')->nullable()->after('is_priority');

            // AI отзыв о работе менеджера
            $table->text('manager_review')->nullable()->after('manager_rating');

            // Когда была проведена оценка
            $table->timestamp('rated_at')->nullable()->after('manager_review');
        });

        // Создаём таблицу логов активности
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50); // status_changed, manager_assigned, viewed, comment_added, etc.
            $table->text('description');
            $table->json('metadata')->nullable(); // Дополнительные данные
            $table->timestamps();

            $table->index(['deal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['is_priority', 'manager_rating', 'manager_review', 'rated_at']);
        });

        Schema::dropIfExists('activity_logs');
    }
};
