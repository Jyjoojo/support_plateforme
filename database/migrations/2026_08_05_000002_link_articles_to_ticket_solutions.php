<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_bases', function (Blueprint $table) {
            $table->uuid('ticket_id')->nullable();
            $table->uuid('commentaire_solution_id')->nullable()->unique();
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
            $table->foreign('commentaire_solution_id')->references('id')->on('commentaires')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('article_bases', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->dropForeign(['commentaire_solution_id']);
            $table->dropUnique(['commentaire_solution_id']);
            $table->dropColumn(['ticket_id', 'commentaire_solution_id']);
        });
    }
};
