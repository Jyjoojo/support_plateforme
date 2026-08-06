<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piece_jointes', function (Blueprint $table) {
            $table->uuid('ajoute_par_id')->nullable();
            $table->foreign('ajoute_par_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('piece_jointes', function (Blueprint $table) {
            $table->dropForeign(['ajoute_par_id']);
            $table->dropColumn('ajoute_par_id');
        });
    }
};
