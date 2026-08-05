<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commentaires', function (Blueprint $table) {
            $table->timestamp('solution_validee_at')->nullable();
            $table->timestamp('solution_rejetee_at')->nullable();
            $table->uuid('solution_validee_par_id')->nullable();
            $table->foreign('solution_validee_par_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commentaires', function (Blueprint $table) {
            $table->dropForeign(['solution_validee_par_id']);
            $table->dropColumn(['solution_validee_at', 'solution_rejetee_at', 'solution_validee_par_id']);
        });
    }
};
