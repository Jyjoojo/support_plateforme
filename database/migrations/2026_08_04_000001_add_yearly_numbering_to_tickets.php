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
        Schema::table('tickets', function (Blueprint $table) {
            $table->smallInteger('annee')->nullable();
            $table->integer('numero')->nullable();
        });

        Schema::create('compteurs_tickets', function (Blueprint $table) {
            $table->smallInteger('annee')->primary();
            $table->integer('dernier_numero')->default(0);
        });

        DB::statement(<<<'SQL'
            WITH tickets_numerotes AS (
                SELECT
                    id,
                    EXTRACT(YEAR FROM created_at)::smallint AS annee,
                    ROW_NUMBER() OVER (
                        PARTITION BY EXTRACT(YEAR FROM created_at)
                        ORDER BY created_at
                    )::integer AS numero
                FROM tickets
            )
            UPDATE tickets
            SET
                annee = tickets_numerotes.annee,
                numero = tickets_numerotes.numero
            FROM tickets_numerotes
            WHERE tickets.id = tickets_numerotes.id
            SQL);

        DB::statement('ALTER TABLE tickets ALTER COLUMN annee SET NOT NULL');
        DB::statement('ALTER TABLE tickets ALTER COLUMN numero SET NOT NULL');

        Schema::table('tickets', function (Blueprint $table) {
            $table->unique(['annee', 'numero'], 'tickets_annee_numero_unique');
        });

        DB::statement(<<<'SQL'
            INSERT INTO compteurs_tickets (annee, dernier_numero)
            SELECT annee, MAX(numero)
            FROM tickets
            GROUP BY annee
            ON CONFLICT (annee) DO UPDATE
            SET dernier_numero = EXCLUDED.dernier_numero
            SQL);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique('tickets_annee_numero_unique');
            $table->dropColumn(['annee', 'numero']);
        });

        Schema::dropIfExists('compteurs_tickets');
    }
};
