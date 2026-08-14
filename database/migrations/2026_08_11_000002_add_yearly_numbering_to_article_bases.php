<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = true;

    public function up(): void
    {
        Schema::table('article_bases', function (Blueprint $table) {
            $table->smallInteger('annee')->nullable();
            $table->integer('numero')->nullable();
        });

        Schema::create('compteurs_articles', function (Blueprint $table) {
            $table->smallInteger('annee')->primary();
            $table->integer('dernier_numero')->default(0);
        });

        DB::statement(<<<'SQL'
            WITH articles_numerotes AS (
                SELECT
                    id,
                    EXTRACT(YEAR FROM created_at)::smallint AS annee,
                    ROW_NUMBER() OVER (
                        PARTITION BY EXTRACT(YEAR FROM created_at)
                        ORDER BY created_at, id
                    )::integer AS numero
                FROM article_bases
            )
            UPDATE article_bases
            SET
                annee = articles_numerotes.annee,
                numero = articles_numerotes.numero
            FROM articles_numerotes
            WHERE article_bases.id = articles_numerotes.id
            SQL);

        DB::statement('ALTER TABLE article_bases ALTER COLUMN annee SET NOT NULL');
        DB::statement('ALTER TABLE article_bases ALTER COLUMN numero SET NOT NULL');

        Schema::table('article_bases', function (Blueprint $table) {
            $table->unique(['annee', 'numero'], 'articles_annee_numero_unique');
        });

        DB::statement(<<<'SQL'
            INSERT INTO compteurs_articles (annee, dernier_numero)
            SELECT annee, MAX(numero)
            FROM article_bases
            GROUP BY annee
            ON CONFLICT (annee) DO UPDATE
            SET dernier_numero = EXCLUDED.dernier_numero
            SQL);
    }

    public function down(): void
    {
        Schema::table('article_bases', function (Blueprint $table) {
            $table->dropUnique('articles_annee_numero_unique');
            $table->dropColumn(['annee', 'numero']);
        });

        Schema::dropIfExists('compteurs_articles');
    }
};
