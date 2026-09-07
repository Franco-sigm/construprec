<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarProyectoRequest;
use App\Models\CaraVano;
use App\Models\Proyecto;
use App\Models\ProyectoCapa;
use App\Models\ProyectoCara;
use App\Services\Presupuesto\GuardarProyectoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proyectos guardados del usuario.
 *
 * Todo se busca acotado al dueño en vez de comprobar el permiso después de
 * encontrarlo: así un proyecto ajeno devuelve 404 y no 403, y no se filtra
 * siquiera que ese identificador existe.
 */
class ProyectoController extends Controller
{
    public function __construct(private readonly GuardarProyectoService $guardar) {}

    public function index(Request $request): JsonResponse
    {
        $proyectos = Proyecto::where('user_id', $request->user()->id)
            ->withCount('caras')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Proyecto $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'estado' => $p->estado,
                'caras' => $p->caras_count,
                'actualizado' => $p->updated_at?->toIso8601String(),
            ]);

        return response()->json(['proyectos' => $proyectos]);
    }

    public function store(GuardarProyectoRequest $request): JsonResponse
    {
        $proyecto = $this->guardar->crear($request->user(), $request->validated());

        return response()->json($this->detalle($proyecto), 201);
    }

    public function show(Request $request, int $proyecto): JsonResponse
    {
        return response()->json($this->detalle($this->delUsuario($request, $proyecto)));
    }

    public function update(GuardarProyectoRequest $request, int $proyecto): JsonResponse
    {
        $actualizado = $this->guardar->actualizar(
            $this->delUsuario($request, $proyecto),
            $request->validated(),
        );

        return response()->json($this->detalle($actualizado));
    }

    public function destroy(Request $request, int $proyecto): JsonResponse
    {
        $this->delUsuario($request, $proyecto)->delete();

        return response()->json(['mensaje' => 'Proyecto eliminado.']);
    }

    private function delUsuario(Request $request, int $id): Proyecto
    {
        return Proyecto::where('user_id', $request->user()->id)
            ->with(['caras.vanos', 'tabiqueria.escuadria', 'capas'])
            ->findOrFail($id);
    }

    /**
     * Devuelve el proyecto en la misma forma que espera el asistente para
     * calcular, así volver a abrirlo es cargar el formulario con lo guardado y
     * no traducir entre dos estructuras distintas.
     *
     * @return array<string, mixed>
     */
    private function detalle(Proyecto $proyecto): array
    {
        $proyecto->loadMissing(['caras.vanos', 'tabiqueria.escuadria', 'capas']);
        $t = $proyecto->tabiqueria;

        return [
            'id' => $proyecto->id,
            'nombre' => $proyecto->nombre,
            'descripcion' => $proyecto->descripcion,
            'estado' => $proyecto->estado,
            'planta' => [
                'ancho_mm' => $proyecto->ancho_mm,
                'largo_mm' => $proyecto->largo_mm,
                'alto_mm' => $proyecto->alto_mm,
                'unidad_ingreso' => $proyecto->unidad_ingreso,
            ],
            'tabiqueria' => $t === null ? null : [
                'escuadria_id' => $t->escuadria_id,
                'escuadria_dintel_id' => $t->escuadria_dintel_id,
                'largo_comercial_mm' => $t->largo_comercial_mm,
                'separacion_mm' => $t->separacion_mm,
                'separacion_unidad' => $t->separacion_unidad_ingreso,
                'solera_inferior' => $t->solera_inferior,
                'soleras_superiores' => $t->soleras_superiores,
                'filas_cadenetas' => $t->filas_cadenetas,
                'merma_pct' => (float) $t->merma_pct,
            ],
            'caras' => $proyecto->caras->map(fn (ProyectoCara $cara) => [
                'nombre' => $cara->nombre,
                'largo_mm' => $cara->largo_mm,
                'alto_mm' => $cara->alto_mm,
                'unidad' => $cara->unidad_ingreso,
                'es_exterior' => $cara->es_exterior,
                'vanos' => $cara->vanos->map(fn (CaraVano $v) => [
                    'tipo' => $v->tipo->value,
                    'ancho_mm' => $v->ancho_mm,
                    'alto_mm' => $v->alto_mm,
                    'antepecho_mm' => $v->antepecho_mm,
                    'cantidad' => $v->cantidad,
                    'unidad' => $v->unidad_ingreso,
                ]),
            ]),
            'capas' => $proyecto->capas->map(fn (ProyectoCapa $capa) => [
                'producto_capa_id' => $capa->producto_capa_id,
                'tipo' => $capa->tipo->value,
                'aplicacion' => $capa->aplicacion->value,
                'nombre' => $capa->nombre,
                'merma_pct' => (float) $capa->merma_pct,
            ]),
        ];
    }
}
