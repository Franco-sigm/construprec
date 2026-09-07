<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Presupuesto;
use App\Models\PresupuestoLinea;
use App\Models\Proyecto;
use App\Services\Presupuesto\ArmarPresupuestoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Presupuestos emitidos a partir de un proyecto guardado.
 *
 * Un presupuesto es una fotografía, no una consulta: guarda el precio con que se
 * cotizó cada material, así que subir el precio de una plancha mañana no cambia
 * el documento que el cliente ya tiene en la mano. Por eso cada cálculo emite
 * uno nuevo en vez de sobrescribir el anterior.
 */
class PresupuestoController extends Controller
{
    public function __construct(private readonly ArmarPresupuestoService $armar) {}

    public function index(Request $request, int $proyecto): JsonResponse
    {
        $presupuestos = $this->proyectoDelUsuario($request, $proyecto)
            ->presupuestos()
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Presupuesto $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'fecha' => $p->fecha?->toDateString(),
                'estado' => $p->estado,
                'moneda' => $p->moneda_destino,
                'total' => (float) $p->total,
            ]);

        return response()->json(['presupuestos' => $presupuestos]);
    }

    public function store(Request $request, int $proyecto): JsonResponse
    {
        $datos = $request->validate([
            'precios' => ['required', 'array', 'min:1'],
            'moneda' => ['required', 'string', 'size:3', 'exists:monedas,codigo'],
            'nombre' => ['nullable', 'string', 'max:150'],
            'fecha' => ['nullable', 'date'],
        ]);

        $presupuesto = $this->armar->para(
            $this->proyectoDelUsuario($request, $proyecto),
            $datos['precios'],
            $datos['moneda'],
            $datos['nombre'] ?? null,
            isset($datos['fecha']) ? Carbon::parse($datos['fecha']) : null,
        );

        return response()->json($this->detalle($presupuesto), 201);
    }

    public function show(Request $request, int $proyecto, int $presupuesto): JsonResponse
    {
        $fila = $this->proyectoDelUsuario($request, $proyecto)
            ->presupuestos()
            ->with('lineas')
            ->findOrFail($presupuesto);

        return response()->json($this->detalle($fila));
    }

    /**
     * Marca el presupuesto como emitido.
     *
     * Desde ahí es un documento histórico y no se recalcula nunca: si cambian
     * los precios o la geometría se emite otro, y los dos quedan para comparar.
     */
    public function emitir(Request $request, int $proyecto, int $presupuesto): JsonResponse
    {
        $fila = $this->proyectoDelUsuario($request, $proyecto)
            ->presupuestos()
            ->findOrFail($presupuesto);

        $fila->update(['estado' => Presupuesto::EMITIDO]);

        return response()->json($this->detalle($fila->fresh(['lineas'])));
    }

    private function proyectoDelUsuario(Request $request, int $id): Proyecto
    {
        return Proyecto::where('user_id', $request->user()->id)->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function detalle(Presupuesto $presupuesto): array
    {
        $presupuesto->loadMissing('lineas');

        return [
            'id' => $presupuesto->id,
            'proyecto_id' => $presupuesto->proyecto_id,
            'nombre' => $presupuesto->nombre,
            'fecha' => $presupuesto->fecha?->toDateString(),
            'estado' => $presupuesto->estado,
            'moneda' => $presupuesto->moneda_destino,
            'total' => (float) $presupuesto->total,
            'lineas' => $presupuesto->lineas->map(fn (PresupuestoLinea $l) => [
                'orden' => $l->orden,
                'origen' => $l->origen,
                'nombre' => $l->nombre_material,
                'unidad_venta' => $l->unidad_venta,
                'magnitud' => (float) $l->magnitud,
                'unidad_magnitud' => $l->unidad_magnitud,
                'rendimiento' => (float) $l->rendimiento,
                'cantidad' => (float) $l->cantidad,
                'cantidad_comprar' => (float) $l->cantidad_comprar,
                'precio_unitario' => (float) $l->precio_unitario,
                'moneda_origen' => $l->moneda_origen,
                'tasa_cambio' => (float) $l->tasa_cambio,
                'subtotal' => (float) $l->subtotal,
                'merma_pct' => (float) $l->merma_pct,
            ]),
        ];
    }
}
