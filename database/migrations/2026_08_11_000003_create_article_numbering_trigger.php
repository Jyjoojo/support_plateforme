<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = true;

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION generer_numero_article()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                annee_courante smallint := EXTRACT(YEAR FROM CURRENT_DATE)::smallint;
                prochain_numero integer;
            BEGIN
                INSERT INTO compteurs_articles (annee, dernier_numero)
                VALUES (annee_courante, 1)
                ON CONFLICT (annee) DO UPDATE
                SET dernier_numero = compteurs_articles.dernier_numero + 1
                RETURNING dernier_numero INTO prochain_numero;

                NEW.annee := annee_courante;
                NEW.numero := prochain_numero;

                RETURN NEW;
            END;
            $$;
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER articles_generer_numero_before_insert
            BEFORE INSERT ON article_bases
            FOR EACH ROW
            EXECUTE FUNCTION generer_numero_article();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS articles_generer_numero_before_insert ON article_bases');
        DB::unprepared('DROP FUNCTION IF EXISTS generer_numero_article()');
    }
};
