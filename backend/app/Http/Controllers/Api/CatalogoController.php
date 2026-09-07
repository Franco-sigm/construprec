<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Escuadria;
use App\Models\ProductoCapa;
use App\Services\Capas\TipoCapa;
use Illuminate\Http\JsonResponse;

/**
 * Datos de referencia con que el asistente arma sus listas desplegables.
 *
 * Va sin autenticación: son medidas de productos que cualquiera puede leer en la
 * barraca, no datos de nadie. Pedir un token para consultarlos sólo obligaría a
 * registrarse antes de poder mirar si la aplicación sirve.
 */
class CatalogoController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Agrupados por tipo de capa: es como los va a pedir la interfaz, un
        // desplegable por paso del asistente.
        $productos = [];
        $tipos = [];

        foreach (TipoCapa::cases() as $tipo) {
            $productos[$tipo->value] = ProductoCapa::where('tipo', $tipo)
                ->where('activo', true)
                ->orderBy('nombre')
                ->get()
                ->map(fn (ProductoCapa $p) => $this->producto($p))
                ->all();

            $tipos[] = [
                'valor' => $tipo->value,
                'etiqueta' => $tipo->etiqueta(),
                'descuenta_vanos_por_defecto' => $tipo->descuentaVanosPorDefecto(),
            ];
        }

        return response()->json([
            'escuadrias' => Escuadria::where('activa', true)
                ->orderBy('estado')
                ->orderBy('nominal')
                ->get()
                ->map(fn (Escuadria $e) => [
                    'id' => $e->id,
                    'nominal' => $e->nominal,
                    'estado' => $e->estado,
                    'descripcion' => $e->descripcion(),
                    'ancho_real_mm' => $e->ancho_real_mm,
                    'alto_real_mm' => $e->alto_real_mm,
                    'largos_comerciales_mm' => $e->largos_comerciales_mm,
                ])
                ->all(),

            'productos' => $productos,
            'tipos_capa' => $tipos,
        ]);
    }

    /** @return array<string, mixed> */
    private function producto(ProductoCapa $p): array
    {
        return [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'unidad_venta' => $p->unidad_venta,
            'rendimiento_m2' => $p->rendimientoM2(),
            // Se expone el ancho útil y no sólo el rendimiento para que la interfaz
            // pueda explicar por qué una tabla de 190 mm rinde como si midiera 160.
            'ancho_mm' => $p->ancho_mm,
            'ancho_util_mm' => $p->anchoUtilMm(),
            'traslape_mm' => $p->traslape_mm,
            'piezas_por_unidad' => $p->piezas_por_unidad,
            'merma_sugerida_pct' => (float) $p->merma_sugerida_pct,
        ];
    }
}
