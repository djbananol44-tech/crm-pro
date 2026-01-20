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
            // AI Lead Scoring
            $table->integer('ai_score')->nullable()->after('ai_summary_at');
            
            // SLA tracking
            $table->timestamp('last_client_message_at')->nullable()->after('ai_score');
            $table->timestamp('last_manager_response_at')->nullable()->after('last_client_message_at');
        });

        Schema::table('users', function (Blueprint $table) {
            // Telegram integration
            $table->string('telegram_chat_id')->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['ai_score', 'last_client_message_at', 'last_manager_response_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telegram_chat_id');
        });
    }
};
