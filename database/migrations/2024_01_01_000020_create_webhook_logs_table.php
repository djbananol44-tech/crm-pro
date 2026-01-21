<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->index()->comment('Источник: meta, telegram');
            $table->string('event_type', 100)->nullable()->comment('Тип события');
            $table->jsonb('payload')->nullable()->comment('Входящие данные');
            $table->integer('response_code')->nullable()->comment('HTTP код ответа');
            $table->text('response_body')->nullable()->comment('Тело ответа');
            $table->string('ip_address', 45)->nullable()->comment('IP отправителя');
            $table->timestamp('processed_at')->nullable()->comment('Время обработки');
            $table->text('error_message')->nullable()->comment('Сообщение об ошибке');
            $table->timestamps();

            $table->index('created_at');
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
