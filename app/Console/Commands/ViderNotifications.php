<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ViderNotifications extends Command
{
    protected $signature = 'notifications:vider';

    protected $description = 'Vide ponctuellement la table des notifications en environnement de développement';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Cette commande est désactivée en production.');

            return self::FAILURE;
        }

        if (! $this->confirm('Vider définitivement toutes les notifications ?')) {
            $this->info('Aucune notification supprimée.');

            return self::SUCCESS;
        }

        DB::statement('TRUNCATE TABLE notifications');

        $this->info('La table notifications a été vidée.');

        return self::SUCCESS;
    }
}
