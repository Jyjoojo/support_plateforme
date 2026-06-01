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
        Schema::create('assignations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->uuid('technicien_id');
            $table->uuid('assigne_par_id')->nullable(); // user_id de l'admin ou technicien qui assigne
            $table->enum('methode', ['manuelle', 'auto_assignation', 'par_specialite'])
                  ->default('manuelle');
            $table->string('motif')->nullable();
            $table->timestamp('date_assignation')->useCurrent();
            $table->timestamps();

            $table->foreign('ticket_id')
                  ->references('id')
                  ->on('tickets')
                  ->onDelete('cascade');

            $table->foreign('technicien_id')
                  ->references('id')
                  ->on('techniciens')
                  ->onDelete('cascade');

            $table->foreign('assigne_par_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index('ticket_id');
            $table->index('technicien_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignations');
    }
};
