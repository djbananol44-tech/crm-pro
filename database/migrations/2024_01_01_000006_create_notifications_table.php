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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->comment('Тип уведомления');
            $table->morphs('notifiable');
            $table->jsonb('data')->comment('Данные уведомления (JSONB)');
            $table->timestamp('read_at')->nullable()->comment('Дата прочтения');
            $table->timestamps();
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
