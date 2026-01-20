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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id')->unique()->comment('ID беседы');
            $table->timestamp('updated_time')->comment('Время последнего обновления');
            $table->enum('platform', ['messenger', 'instagram'])->comment('Платформа');
            $table->string('link')->nullable()->comment('Ссылка на беседу (fb.com/messages/t/id)');
            $table->timestamps();
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
