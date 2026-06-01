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
        //
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('titre');
            $table->text('description');
            $table->enum('statut', ['nouveau', 'en_cours', 'en_attente', 'resolu', 'ferme'])
                  ->default('nouveau');
            $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])
                  ->default('normale');
            $table->enum('source_creation', ['client', 'technicien', 'administrateur'])
                  ->default('client');

            // Créateur du ticket (user_id polymorphe simplifié)
            $table->uuid('createur_id');
            $table->string('createur_type'); // App\Models\Client | Technicien | Administrateur

            // Client concerné par le ticket (peut différer du créateur)
            $table->uuid('client_id')->nullable();

            $table->uuid('categorie_id')->nullable();
            $table->timestamp('date_resolution')->nullable();
            $table->softDeletes();

            $table->foreign('client_id')
                  ->references('id')
                  ->on('clients')
                  ->onDelete('set null');

            $table->foreign('categorie_id')
                  ->references('id')
                  ->on('categories')
                  ->onDelete('set null');

            // Index pour les filtres fréquents
            $table->index('statut');
            $table->index('priorite');
            $table->index('source_creation');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('tickets', function (Blueprint $table) {
            // On supprime d'abord les contraintes de clés étrangères
            $table->dropForeign(['client_id']);
            $table->dropForeign(['categorie_id']);
            // Puis on supprime les colonnes ajoutées (y compris le deleted_at de softDeletes)
            $table->dropColumn([
                'titre', 'description', 'statut', 'priorite', 'source_creation', 
                'createur_id', 'createur_type', 'client_id', 'categorie_id', 
                'date_resolution', 'deleted_at'
            ]);
        });
    }
};
