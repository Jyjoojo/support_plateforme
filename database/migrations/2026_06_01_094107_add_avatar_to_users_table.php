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
        Schema::table('users', function (Blueprint $table) {
            // Renommage de la colonne 'name' vers 'nom'
            $table->renameColumn('name', 'nom');
            $table->string('prenom')->after('name');
            $table->enum('role', ['administrateur', 'technicien', 'client']);
            $table->string('telephone')->nullable();
            $table->boolean('actif')->default(true);
            $table->string('avatar')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('nom', 'name');
            $table->dropColumn(['prenom', 'role', 'telephone', 'actif', 'avatar']);
        });
    }
};
