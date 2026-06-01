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
        Schema::create('article_bases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('technicien_id')->nullable(); // rédigé par
            $table->uuid('categorie_id')->nullable();  // classé dans
            $table->string('titre');
            $table->longText('contenu');
            $table->string('mots_cles')->nullable(); // séparés par virgule
            $table->unsignedInteger('vues')->default(0);
            $table->boolean('publie')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('technicien_id')
                  ->references('id')
                  ->on('techniciens')
                  ->onDelete('set null');

            $table->foreign('categorie_id')
                  ->references('id')
                  ->on('categories')
                  ->onDelete('set null');

            // Full-text search (PostgreSQL)
            $table->fullText(['titre', 'contenu', 'mots_cles']);

            $table->index('publie');
            $table->index('categorie_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_bases');
    }
};
