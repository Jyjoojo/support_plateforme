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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id'); // destinataire
            $table->uuid('ticket_id')->nullable(); // ticket concerné (optionnel)
            $table->string('message');
            $table->enum('type', [
                'nouveau_ticket',
                'ticket_assigne',
                'nouveau_commentaire',
                'statut_change',
                'ticket_resolu',
                'rappel'
            ]);
            $table->boolean('est_lue')->default(false);
            $table->timestamp('date_envoi')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('ticket_id')
                  ->references('id')
                  ->on('tickets')
                  ->onDelete('cascade');

            $table->index(['user_id', 'est_lue']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
