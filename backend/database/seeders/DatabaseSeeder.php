<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Datos de referencia: no dependen del usuario y tienen que existir antes
        // de que se pueda crear cualquier proyecto o presupuesto.
        $this->call([
            MonedaSeeder::class,
            EscuadriaSeeder::class,
        ]);
    }
}
