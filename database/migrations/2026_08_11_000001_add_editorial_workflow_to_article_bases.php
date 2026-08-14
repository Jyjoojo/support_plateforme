<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_bases', function (Blueprint $table) {
            $table->uuid('auteur_id')->nullable();
            $table->string('statut_editorial', 32)->default('brouillon')->index();
            $table->timestamp('soumis_at')->nullable();
            $table->uuid('soumis_par_id')->nullable();
            $table->timestamp('valide_at')->nullable();
            $table->uuid('valide_par_id')->nullable();
            $table->text('motif_refus')->nullable();
            $table->timestamp('publie_at')->nullable();
            $table->timestamp('archive_at')->nullable();
            $table->uuid('archive_par_id')->nullable();

            $table->foreign('auteur_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('soumis_par_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('valide_par_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('archive_par_id')->references('id')->on('users')->nullOnDelete();
        });

        $techniciens = DB::table('techniciens')->pluck('user_id', 'id');

        DB::table('article_bases')->orderBy('id')->get()->each(function (object $article) use ($techniciens): void {
            $archive = $article->deleted_at !== null;
            $publie = (bool) $article->publie && ! $archive;

            DB::table('article_bases')->where('id', $article->id)->update([
                'auteur_id' => $article->technicien_id ? $techniciens->get($article->technicien_id) : null,
                'statut_editorial' => $archive ? 'archive' : ($publie ? 'publie' : 'brouillon'),
                'publie_at' => $publie ? $article->updated_at : null,
                'archive_at' => $archive ? $article->deleted_at : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('article_bases', function (Blueprint $table) {
            $table->dropForeign(['auteur_id']);
            $table->dropForeign(['soumis_par_id']);
            $table->dropForeign(['valide_par_id']);
            $table->dropForeign(['archive_par_id']);
            $table->dropColumn([
                'auteur_id',
                'statut_editorial',
                'soumis_at',
                'soumis_par_id',
                'valide_at',
                'valide_par_id',
                'motif_refus',
                'publie_at',
                'archive_at',
                'archive_par_id',
            ]);
        });
    }
};
