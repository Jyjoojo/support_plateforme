<?php

namespace Database\Seeders;

use App\Models\Technicien;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TechnicienSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Technicien::factory()->count(5)->create();
    }
}
