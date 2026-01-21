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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service')->comment('Источник: bot, api, db, queue, meta, telegram');
            $table->string('level')->default('info')->comment('Уровень: debug, info, warning, error, critical');
            $table->string('action')->nullable()->comment('Действие');
            $table->text('message')->comment('Сообщение');
            $table->jsonb('context')->nullable()->comment('Дополнительные данные');
            $table->string('ip_address')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->index('service');
            $table->index('level');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
