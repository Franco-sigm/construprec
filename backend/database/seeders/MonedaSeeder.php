<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MonedaSeeder extends Seeder
{
    /**
     * Monedas ISO 4217 con las que puede expresarse un presupuesto.
     *
     * `decimales` no es cosmetico: define como se redondea el total. El peso
     * chileno y el guarani no admiten fraccion, y cotizar "$1.500,50" en pesos
     * produce un monto que nadie puede pagar.
     */
    public function run(): void
    {
        $monedas = [
            // codigo, nombre, simbolo, decimales
            ['CLP', 'Peso chileno',      '$',    0],
            ['USD', 'Dolar estadounidense', 'US$', 2],
            ['EUR', 'Euro',              '€',    2],
            ['PEN', 'Sol peruano',       'S/',   2],
            ['BOB', 'Boliviano',         'Bs',   2],
            ['ARS', 'Peso argentino',    '$',    2],
            ['BRL', 'Real brasileno',    'R$',   2],
            ['COP', 'Peso colombiano',   '$',    2],
            ['MXN', 'Peso mexicano',     '$',    2],
            ['PYG', 'Guarani',           '₲',    0],
        ];

        foreach ($monedas as [$codigo, $nombre, $simbolo, $decimales]) {
            DB::table('monedas')->updateOrInsert(
                ['codigo' => $codigo],
                [
                    'nombre' => $nombre,
                    'simbolo' => $simbolo,
                    'decimales' => $decimales,
                    'activa' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
