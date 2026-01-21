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
        // Добавляем колонку is_encrypted в settings
        if (!Schema::hasColumn('settings', 'is_encrypted')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->boolean('is_encrypted')->default(false)->after('value')
                    ->comment('Зашифровано ли значение');
            });
        }

        // Создаём таблицу аудит-логов
        Schema::create('setting_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->index()->comment('Ключ настройки');
            $table->string('action', 20)->comment('created|updated|deleted');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Кто изменил');
            $table->boolean('is_secret')->default(false)->comment('Секретный ключ');
            $table->boolean('had_value_before')->default(false)->comment('Было значение до изменения');
            $table->boolean('has_value_after')->default(false)->comment('Есть значение после изменения');
            $table->string('ip_address', 45)->nullable()->comment('IP адрес');
            $table->text('user_agent')->nullable()->comment('User Agent');
            $table->timestamps();

            // Индекс для быстрого поиска
            $table->index(['setting_key', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setting_audit_logs');

        if (Schema::hasColumn('settings', 'is_encrypted')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('is_encrypted');
            });
        }
    }
};
