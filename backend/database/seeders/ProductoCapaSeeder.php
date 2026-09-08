<?php

namespace Database\Seeders;

use App\Services\Capas\TipoCapa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductoCapaSeeder extends Seeder
{
    /**
     * Productos habituales del mercado chileno para cada capa.
     *
     * PRECISION: las medidas de plancha (1,22 x 2,44 en terciado y OSB, 1,20 x
     * 2,40 en yeso-carton) y la tabla de siding de 190 x 3660 x 6 mm estan
     * tomadas de fichas de proveedores chilenos. Las piezas por caja del siding
     * NO se pudieron confirmar: ninguna ficha publica las declara, asi que va
     * como tabla suelta y quien compre por caja ajusta el numero. Las mermas son
     * sugerencias de arranque, no verdades.
     */
    public function run(): void
    {
        $productos = [
            // --- arriostramiento / tablero estructural ---
            [TipoCapa::Arriostramiento, 'OSB estructural 9,5 mm 1,22 x 2,44', 'plancha', 2440, 1220, 9.5, 0, 1, null, 5],
            [TipoCapa::Arriostramiento, 'OSB estructural 11,1 mm 1,22 x 2,44', 'plancha', 2440, 1220, 11.1, 0, 1, null, 5],
            [TipoCapa::Arriostramiento, 'Terciado estructural CD 12 mm 1,22 x 2,44', 'plancha', 2440, 1220, 12.0, 0, 1, null, 5],
            [TipoCapa::Arriostramiento, 'Terciado estructural CD 15 mm 1,22 x 2,44', 'plancha', 2440, 1220, 15.0, 0, 1, null, 5],

            // --- revestimiento interior ---
            // El yeso-carton no es la unica opcion: el terciado ranurado imita
            // tablas de madera y se usa igual, con la ventaja de que aporta
            // rigidez en vez de solo cerrar.
            [TipoCapa::RevestimientoInterior, 'Yeso-cartón 8 mm 1,20 x 2,40', 'plancha', 2400, 1200, 8.0, 0, 1, null, 8],
            [TipoCapa::RevestimientoInterior, 'Yeso-cartón 12,5 mm 1,20 x 2,40', 'plancha', 2400, 1200, 12.5, 0, 1, null, 8],
            [TipoCapa::RevestimientoInterior, 'Terciado ranurado clásico 12 mm 1,22 x 2,44', 'plancha', 2440, 1220, 12.0, 0, 1, null, 8],
            [TipoCapa::RevestimientoInterior, 'Terciado ranurado colonial 9 mm 1,22 x 2,44', 'plancha', 2440, 1220, 9.0, 0, 1, null, 8],
            [TipoCapa::RevestimientoInterior, 'Volcanita RH 12,5 mm 1,20 x 2,40', 'plancha', 2400, 1200, 12.5, 0, 1, null, 8],

            // --- revestimiento exterior ---
            // El siding se instala traslapado: de sus 190 mm de ancho solo quedan
            // 160 a la vista con 30 de solape. Por eso el traslape es un campo y
            // no un supuesto escondido en el rendimiento.
            [TipoCapa::RevestimientoExterior, 'Siding fibrocemento 190 x 3660 x 6 mm', 'tabla', 3660, 190, 6.0, 30, 1, null, 10],
            [TipoCapa::RevestimientoExterior, 'Siding fibrocemento 190 x 3000 x 6 mm', 'tabla', 3000, 190, 6.0, 30, 1, null, 10],
            [TipoCapa::RevestimientoExterior, 'Siding fibrocemento 190 x 3660 mm, caja de 10', 'caja', 3660, 190, 6.0, 30, 10, null, 10],
            [TipoCapa::RevestimientoExterior, 'Zinc acanalado 0,35 mm 0,85 x 3,00', 'plancha', 3000, 850, 0.35, 100, 1, null, 10],
            [TipoCapa::RevestimientoExterior, 'Terciado ranurado exterior 12 mm 1,22 x 2,44', 'plancha', 2440, 1220, 12.0, 0, 1, null, 8],

            // --- aislante ---
            // Los rollos declaran su rendimiento en la etiqueta: no hay pieza que
            // medir, y derivarlo de una geometria inventada seria peor dato.
            [TipoCapa::Aislante, 'Lana de vidrio 50 mm, rollo 12 m²', 'rollo', null, null, 50.0, 0, 1, 12.0, 5],
            [TipoCapa::Aislante, 'Lana de vidrio 80 mm, rollo 9 m²', 'rollo', null, null, 80.0, 0, 1, 9.0, 5],
            [TipoCapa::Aislante, 'Lana mineral 50 mm, rollo 13,5 m²', 'rollo', null, null, 50.0, 0, 1, 13.5, 5],
            [TipoCapa::Aislante, 'Poliestireno expandido 50 mm 1,00 x 2,00', 'plancha', 2000, 1000, 50.0, 0, 1, null, 5],

            // --- cubierta ---
            // El zinc acanalado mide 851 mm y se monta traslapando ondas: de ahí
            // quedan unos 751 a la vista. Las costaneras van entre 1,0 y 1,2 m
            // según pendiente y carga; se propone 1,1 como punto medio.
            [TipoCapa::Cubierta, 'Zinc acanalado 0,35 mm 0,851 x 3,00 m', 'plancha', 3000, 851, 0.35, 100, 1, null, 8, 1100, false],
            [TipoCapa::Cubierta, 'Zinc acanalado 0,35 mm 0,851 x 3,66 m', 'plancha', 3660, 851, 0.35, 100, 1, null, 8, 1100, false],

            // El 5V declara su avance útil directo —819 mm— que ya viene neto del
            // traslape, así que acá el traslape va en cero y no se descuenta dos
            // veces.
            [TipoCapa::Cubierta, 'Zincalum 5V 0,35 mm, avance útil 819 mm x 3,00 m', 'plancha', 3000, 819, 0.35, 0, 1, null, 8, 1100, false],

            // La teja asfáltica no se clava sobre costaneras sino sobre tablero
            // continuo, así que su separación va nula y se marca que lo exige: sin
            // eso se cotizaría un techo que no se puede armar.
            [TipoCapa::Cubierta, 'Teja asfáltica, paquete de 21 (rinde 3 m²)', 'paquete', null, null, null, 0, 1, 3.0, 10, null, true],

            [TipoCapa::Cubierta, 'Teja de arcilla, por unidad', 'teja', null, null, null, 0, 1, 0.077, 10, 350, false],
            [TipoCapa::Cubierta, 'Panel PV-4, a medida', 'm²', null, null, null, 0, 1, 1.0, 5, 1500, false],

            // --- membrana hidrófuga ---
            [TipoCapa::Membrana, 'Membrana hidrófuga 1,5 x 50 m', 'rollo', null, null, null, 0, 1, 75.0, 10],
            [TipoCapa::Membrana, 'Fieltro asfáltico 15 lb, rollo 27 m²', 'rollo', null, null, null, 0, 1, 27.0, 10],
        ];

        foreach ($productos as $fila) {
            // Las filas de cubierta traen dos campos más; el resto los deja en su
            // valor por defecto.
            [$tipo, $nombre, $unidad, $largo, $ancho, $espesor, $traslape, $piezas, $rendimiento, $merma] = $fila;
            $separacionCostaneras = $fila[10] ?? null;
            $requiereTablero = $fila[11] ?? false;

            DB::table('productos_capa')->updateOrInsert(
                ['nombre' => $nombre, 'pais' => 'CL'],
                [
                    'tipo' => $tipo->value,
                    'unidad_venta' => $unidad,
                    'largo_mm' => $largo,
                    'ancho_mm' => $ancho,
                    'espesor_mm' => $espesor,
                    'traslape_mm' => $traslape,
                    'separacion_costaneras_mm' => $separacionCostaneras,
                    'requiere_tablero' => $requiereTablero,
                    'piezas_por_unidad' => $piezas,
                    'rendimiento_m2' => $rendimiento,
                    'fraccionable' => false,
                    'merma_sugerida_pct' => $merma,
                    'activo' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
