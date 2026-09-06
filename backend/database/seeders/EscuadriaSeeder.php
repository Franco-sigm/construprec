<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EscuadriaSeeder extends Seeder
{
    /**
     * Catalogo base de escuadrias de pino radiata para tabiqueria en Chile.
     *
     * Las medidas reales son el dato critico del despiece: el conteo de pies
     * derechos y el ancho que ocupa cada pieza salen de ellas, no de las
     * nominales. La referencia formal es la NCh2824 (Maderas - Pino radiata -
     * unidades, dimensiones y tolerancias), cuya tabla A.1 relaciona la
     * denominacion comercial en pulgadas con la medida efectiva de cada estado.
     *
     * PRECISION DE ESTOS VALORES: el 2x4 seco cepillado en 41 x 90 mm y el 2x3
     * seco cepillado en 41 x 65 mm aparecen repetidos en fichas de proveedores
     * chilenos. El 3x4 NO se pudo confirmar en catalogo: casi ninguna barraca lo
     * publica, asi que sus medidas estan interpoladas del patron por pulgada que
     * dejan las otras (2" -> 41, 3" -> 65, 4" -> 90 en cepillado; unos 5 mm mas en
     * verde). Conviene medir una pieza real antes de cotizar en serio: la norma
     * ademas admite tolerancia segun humedad.
     */
    public function run(): void
    {
        // Largos con que se vende. El despiece elige el que deje menos recorte, asi
        // que ofrecer un largo que la barraca no tiene produce una lista de compra
        // que no se puede ejecutar: conviene sacar de aca lo que no se consiga.
        $largos = [2440, 3200, 4000];

        $escuadrias = [
            // nominal, ancho_mm, alto_mm, estado
            //
            // Seco cepillado: el estado habitual para tabiqueria. Ya viene secado en
            // camara, asi que no se tuerce despues de montado.
            ['2x3', 41, 65, 'seco_cepillado'],
            ['3x4', 65, 90, 'seco_cepillado'],
            ['2x4', 41, 90, 'seco_cepillado'],

            // Verde: mas barato y de mayor seccion, pero encoge y se tuerce al secar
            // dentro del tabique. Se incluye porque se usa igual, no porque convenga.
            ['2x3', 45, 70, 'verde'],
            ['3x4', 70, 95, 'verde'],
            ['2x4', 45, 95, 'verde'],
        ];

        foreach ($escuadrias as [$nominal, $ancho, $alto, $estado]) {
            DB::table('escuadrias')->updateOrInsert(
                ['nominal' => $nominal, 'estado' => $estado, 'pais' => 'CL'],
                [
                    'ancho_real_mm' => $ancho,
                    'alto_real_mm' => $alto,
                    'largos_comerciales_mm' => json_encode($largos),
                    'activa' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
