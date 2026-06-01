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
        // Les statistiques sont calculées à la volée via des requêtes Eloquent
        // Cette table sert de snapshot/cache pour les rapports périodiques
        Schema::create('statistiques', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('genere_par_id')->nullable(); // administrateur
            $table->unsignedInteger('total_tickets')->default(0);
            $table->unsignedInteger('tickets_resolus')->default(0);
            $table->unsignedInteger('tickets_en_cours')->default(0);
            $table->float('temps_moyen_resolution')->nullable(); // en heures
            $table->date('periode_debut')->nullable();
            $table->date('periode_fin')->nullable();
            $table->uuid('filtre_client_id')->nullable();
            $table->uuid('filtre_categorie_id')->nullable();
            $table->timestamps();

            $table->foreign('genere_par_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('filtre_client_id')
                  ->references('id')
                  ->on('clients')
                  ->onDelete('set null');

            $table->foreign('filtre_categorie_id')
                  ->references('id')
                  ->on('categories')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statistiques');
    }
};
