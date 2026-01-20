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
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->onDelete('cascade')->comment('ID контакта');
            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade')->comment('ID беседы');
            $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('set null')->comment('ID менеджера');
            $table->enum('status', ['New', 'In Progress', 'Closed'])->default('New')->comment('Статус сделки');
            $table->text('comment')->nullable()->comment('Комментарий');
            $table->timestamp('reminder_at')->nullable()->comment('Напоминание на дату');
            $table->timestamps();
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
