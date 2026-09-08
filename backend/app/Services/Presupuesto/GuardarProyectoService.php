<?php

namespace App\Services\Presupuesto;

use App\Models\CaraVano;
use App\Models\Escuadria;
use App\Models\ProductoCapa;
use App\Models\Proyecto;
use App\Models\ProyectoCapa;
use App\Models\ProyectoCara;
use App\Models\TabiqueriaConfig;
use App\Models\TechumbreConfig;
use App\Models\User;
use App\Support\Medida;
use Illuminate\Support\Facades\DB;

/**
 * Escribe en la base la geometría que armó el usuario en el asistente.
 *
 * Al actualizar, las caras, los vanos y las capas se borran y se vuelven a
 * crear. Podría hacerse comparando lo viejo con lo nuevo y tocando sólo lo que
 * cambió, pero eso exige que cada fila traiga un identificador estable desde el
 * formulario, y el asistente permite agregar y quitar vanos libremente. Rehacer
 * los hijos es más simple y no puede dejar un vano huérfano de una versión
 * anterior. Va todo en una transacción, así que no hay instante en que el
 * proyecto esté a medias.
 */
final class GuardarProyectoService
{
    public function crear(User $usuario, array $datos): Proyecto
    {
        return DB::transaction(function () use ($usuario, $datos) {
            $proyecto = Proyecto::create([
                'user_id' => $usuario->id,
                ...$this->camposDelProyecto($datos),
            ]);

            $this->escribirHijos($proyecto, $datos);

            return $proyecto->fresh(['caras.vanos', 'tabiqueria', 'capas']);
        });
    }

    public function actualizar(Proyecto $proyecto, array $datos): Proyecto
    {
        return DB::transaction(function () use ($proyecto, $datos) {
            $proyecto->update($this->camposDelProyecto($datos));

            // Los vanos se van con sus caras por la llave foránea en cascada.
            $proyecto->caras()->delete();
            $proyecto->capas()->delete();
            $proyecto->tabiqueria()->delete();
            $proyecto->techumbre()->delete();

            $this->escribirHijos($proyecto, $datos);

            return $proyecto->fresh(['caras.vanos', 'tabiqueria', 'capas']);
        });
    }

    /** @return array<string, mixed> */
    private function camposDelProyecto(array $datos): array
    {
        $planta = $datos['planta'] ?? [];

        return [
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'ancho_mm' => $planta['ancho_mm'] ?? null,
            'largo_mm' => $planta['largo_mm'] ?? null,
            'alto_mm' => $planta['alto_mm'] ?? null,
            'unidad_ingreso' => $planta['unidad_ingreso'] ?? 'm',
        ];
    }

    private function escribirHijos(Proyecto $proyecto, array $datos): void
    {
        $this->escribirTabiqueria($proyecto, $datos['tabiqueria']);
        $this->escribirCaras($proyecto, $datos['caras']);
        $this->escribirCapas($proyecto, $datos['capas'] ?? [], 'muros');

        // La techumbre es opcional: un proyecto puede quedarse en los muros.
        if (isset($datos['techumbre'])) {
            $this->escribirTechumbre($proyecto, $datos['techumbre']);
            $this->escribirCapas($proyecto, $datos['techumbre']['capas'] ?? [], 'techumbre');
        }
    }

    private function escribirTechumbre(Proyecto $proyecto, array $datos): void
    {
        $u = $datos['unidad'] ?? 'm';
        $us = $datos['separacion_unidad'] ?? $u;
        $cercha = Escuadria::findOrFail($datos['escuadria_id']);

        TechumbreConfig::create([
            'proyecto_id' => $proyecto->id,
            'aguas' => $datos['aguas'],
            'luz_mm' => Medida::de($datos['luz'], $u)->mm,
            'largo_mm' => Medida::de($datos['largo'], $u)->mm,
            'altura_cumbrera_mm' => Medida::de($datos['altura_cumbrera'], $u)->mm,
            'alero_mm' => Medida::de($datos['alero'] ?? 0, $u)->mm,
            'unidad_ingreso' => $u,
            'escuadria_id' => $cercha->id,
            // Igual que en la tabiquería: se copia la medida real, no sólo el id.
            'escuadria_ancho_mm' => $cercha->ancho_real_mm,
            'escuadria_alto_mm' => $cercha->alto_real_mm,
            'escuadria_costanera_id' => $datos['escuadria_costanera_id'] ?? null,
            'largo_comercial_mm' => $datos['largo_comercial_mm'] ?? max($cercha->largos_comerciales_mm),
            'separacion_cerchas_mm' => Medida::de($datos['separacion_cerchas'], $us)->mm,
            'separacion_costaneras_mm' => Medida::de($datos['separacion_costaneras'], $us)->mm,
            'separacion_unidad_ingreso' => $us,
            'merma_pct' => $datos['merma_pct'] ?? 0,
        ]);
    }

    private function escribirTabiqueria(Proyecto $proyecto, array $datos): void
    {
        $escuadria = Escuadria::findOrFail($datos['escuadria_id']);

        TabiqueriaConfig::create([
            'proyecto_id' => $proyecto->id,
            'escuadria_id' => $escuadria->id,
            // Se copian las medidas reales de la escuadría, no sólo su id: si
            // mañana se corrige el catálogo, un proyecto ya guardado no puede
            // cambiar de dimensiones por debajo.
            'escuadria_ancho_mm' => $escuadria->ancho_real_mm,
            'escuadria_alto_mm' => $escuadria->alto_real_mm,
            'largo_comercial_mm' => $datos['largo_comercial_mm'] ?? min($escuadria->largos_comerciales_mm),
            'separacion_mm' => Medida::de($datos['separacion'], $datos['separacion_unidad'] ?? 'm')->mm,
            'separacion_unidad_ingreso' => $datos['separacion_unidad'] ?? 'm',
            'solera_inferior' => $datos['solera_inferior'] ?? true,
            'soleras_superiores' => $datos['soleras_superiores'] ?? 1,
            'escuadria_dintel_id' => $datos['escuadria_dintel_id'] ?? null,
            'merma_pct' => $datos['merma_pct'] ?? 0,
            'filas_cadenetas' => $datos['filas_cadenetas'] ?? 1,
            'piezas_por_esquina' => $datos['piezas_por_esquina'] ?? 3,
        ]);
    }

    private function escribirCaras(Proyecto $proyecto, array $caras): void
    {
        foreach (array_values($caras) as $i => $cara) {
            $unidad = $cara['unidad'] ?? 'm';

            $fila = ProyectoCara::create([
                'proyecto_id' => $proyecto->id,
                'orden' => $i + 1,
                'nombre' => $cara['nombre'] ?? 'Cara '.($i + 1),
                'largo_mm' => Medida::de($cara['largo'], $unidad)->mm,
                'alto_mm' => Medida::de($cara['alto'], $unidad)->mm,
                'unidad_ingreso' => $unidad,
                'es_exterior' => $cara['es_exterior'] ?? true,
            ]);

            foreach ($cara['vanos'] ?? [] as $vano) {
                $u = $vano['unidad'] ?? $unidad;

                CaraVano::create([
                    'cara_id' => $fila->id,
                    'tipo' => $vano['tipo'],
                    'ancho_mm' => Medida::de($vano['ancho'], $u)->mm,
                    'alto_mm' => Medida::de($vano['alto'], $u)->mm,
                    'antepecho_mm' => Medida::de($vano['antepecho'] ?? 0, $u)->mm,
                    'cantidad' => $vano['cantidad'] ?? 1,
                    // Sin esto, reabrir el proyecto devolvía los vanos repartidos
                    // automáticamente y había que volver a ubicarlos uno por uno.
                    'desde_tramo' => $vano['desde_tramo'] ?? null,
                    'unidad_ingreso' => $u,
                ]);
            }
        }
    }

    private function escribirCapas(Proyecto $proyecto, array $capas, string $etapa): void
    {
        foreach (array_values($capas) as $i => $capa) {
            $producto = ProductoCapa::findOrFail($capa['producto_capa_id']);

            ProyectoCapa::create([
                'proyecto_id' => $proyecto->id,
                'etapa' => $etapa,
                'producto_capa_id' => $producto->id,
                'tipo' => $producto->tipo,
                'aplicacion' => $capa['aplicacion'] ?? 'exterior',
                // Igual que con la escuadría: el nombre y el rendimiento se copian
                // para que la capa sobreviva intacta si el producto se borra o se
                // corrige en el catálogo.
                'nombre' => $producto->nombre,
                'unidad_venta' => $producto->unidad_venta,
                'rendimiento' => $producto->rendimientoM2(),
                'unidad_rendimiento' => 'm2',
                'fraccionable' => $producto->fraccionable,
                'merma_pct' => $capa['merma_pct'] ?? $producto->merma_sugerida_pct,
                'descuenta_vanos' => $capa['descuenta_vanos'] ?? null,
                'orden' => $i,
            ]);
        }
    }
}
