<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdministrateurSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->administrateur()->create([
            'nom' => 'Kouehi',
            'prenom' => 'Ange Joel',
            'email' => 'admin@example.com',
            'telephone' => '+33123456789',
            'actif' => true,
        ]);

        User::factory()->administrateur()->create([
            'nom' => 'Support',
            'prenom' => 'Manager',
            'email' => 'manager@example.com',
            'telephone' => '+33987654321',
            'actif' => true,
        ]);
    }
}
