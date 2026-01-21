<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Добавляет полнотекстовый поиск (FTS) для deals.
 *
 * Индексируемые поля:
 * - contact.name, contact.first_name, contact.last_name
 * - deal.comment
 * - deal.ai_summary
 * - deal.last_message_text (новое поле)
 * - deal.status
 *
 * Использует Postgres tsvector + GIN индекс для быстрого поиска.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            // Поле для последнего сообщения клиента (кэш)
            if (!Schema::hasColumn('deals', 'last_message_text')) {
                $table->text('last_message_text')->nullable()->after('last_manager_response_at');
            }

            // Дополнительные поля для расширенного анализа (могут уже существовать)
            if (!Schema::hasColumn('deals', 'ai_intent')) {
                $table->string('ai_intent')->nullable()->after('ai_score');
            }
            if (!Schema::hasColumn('deals', 'ai_objections')) {
                $table->json('ai_objections')->nullable()->after('ai_intent');
            }
            if (!Schema::hasColumn('deals', 'ai_next_action')) {
                $table->string('ai_next_action')->nullable()->after('ai_objections');
            }
            if (!Schema::hasColumn('deals', 'analysis_failed_at')) {
                $table->timestamp('analysis_failed_at')->nullable()->after('ai_summary_at');
            }
        });

        // Добавляем tsvector колонку через raw SQL (Laravel Schema не поддерживает tsvector)
        if (!Schema::hasColumn('deals', 'search_vector')) {
            DB::statement('ALTER TABLE deals ADD COLUMN search_vector tsvector');
        }

        // Создаем GIN индекс для быстрого поиска
        $indexExists = DB::selectOne("
            SELECT 1 FROM pg_indexes WHERE indexname = 'deals_search_vector_gin_idx'
        ");
        if (!$indexExists) {
            DB::statement('CREATE INDEX deals_search_vector_gin_idx ON deals USING GIN (search_vector)');
        }

        // Создаем функцию для обновления search_vector (CREATE OR REPLACE безопасен)
        DB::statement("
            CREATE OR REPLACE FUNCTION deals_search_vector_update() RETURNS trigger AS \$\$
            DECLARE
                contact_name text;
                contact_first_name text;
                contact_last_name text;
                contact_psid text;
            BEGIN
                -- Получаем данные контакта
                SELECT name, first_name, last_name, psid 
                INTO contact_name, contact_first_name, contact_last_name, contact_psid
                FROM contacts WHERE id = NEW.contact_id;

                -- Формируем search_vector с весами:
                -- A (highest): имя контакта
                -- B: AI summary, intent
                -- C: комментарий, последнее сообщение
                -- D (lowest): psid, статус
                NEW.search_vector := 
                    setweight(to_tsvector('russian', coalesce(contact_name, '')), 'A') ||
                    setweight(to_tsvector('russian', coalesce(contact_first_name, '')), 'A') ||
                    setweight(to_tsvector('russian', coalesce(contact_last_name, '')), 'A') ||
                    setweight(to_tsvector('russian', coalesce(NEW.ai_summary, '')), 'B') ||
                    setweight(to_tsvector('russian', coalesce(NEW.ai_intent, '')), 'B') ||
                    setweight(to_tsvector('russian', coalesce(NEW.comment, '')), 'C') ||
                    setweight(to_tsvector('russian', coalesce(NEW.last_message_text, '')), 'C') ||
                    setweight(to_tsvector('simple', coalesce(contact_psid, '')), 'D') ||
                    setweight(to_tsvector('simple', coalesce(NEW.status, '')), 'D');
                
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Создаем триггер для автоматического обновления (если не существует)
        $triggerExists = DB::selectOne("
            SELECT 1 FROM pg_trigger WHERE tgname = 'deals_search_vector_trigger'
        ");
        if (!$triggerExists) {
            DB::statement('
                CREATE TRIGGER deals_search_vector_trigger
                BEFORE INSERT OR UPDATE ON deals
                FOR EACH ROW EXECUTE FUNCTION deals_search_vector_update();
            ');
        }

        // Индексируем существующие записи
        DB::statement('
            UPDATE deals SET 
                search_vector = search_vector,
                updated_at = updated_at
            WHERE TRUE;
        ');
    }

    public function down(): void
    {
        // Удаляем триггер и функцию
        DB::statement('DROP TRIGGER IF EXISTS deals_search_vector_trigger ON deals');
        DB::statement('DROP FUNCTION IF EXISTS deals_search_vector_update()');

        // Удаляем индекс и колонку
        DB::statement('DROP INDEX IF EXISTS deals_search_vector_gin_idx');
        DB::statement('ALTER TABLE deals DROP COLUMN IF EXISTS search_vector');

        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn([
                'last_message_text',
                'ai_intent',
                'ai_objections',
                'ai_next_action',
                'analysis_failed_at',
            ]);
        });
    }
};
