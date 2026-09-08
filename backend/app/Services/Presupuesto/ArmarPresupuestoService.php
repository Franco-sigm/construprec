<?php

namespace App\Services\Presupuesto;

use App\Models\Presupuesto;
use App\Models\PresupuestoLinea;
use App\Models\Proyecto;
use App\Services\Monedas\TasaCambioService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Toma los precios que escribio el usuario y arma el presupuesto.
 *
 * Es el paso corto: las cantidades ya estaban resueltas antes de preguntar un
 * solo precio. Aca solo se multiplica, se convierte de moneda y se congela.
 *
 * Cada linea guarda una fotografia del material y del precio. Sin esa copia,
 * subir el precio de una plancha cambiaria todos los presupuestos ya entregados,
 * incluido el que el cliente tiene en la mano.
 */
final class ArmarPresupuestoService
{
    public function __construct(
        private readonly CalculoProyectoService $calculo,
        private readonly TasaCambioService $tasas,
    ) {}

    /**
     * @param  array<string, mixed>  $precios  indexado por la clave del material
     */
    public function para(
        Proyecto $proyecto,
        array $precios,
        string $monedaDestino,
        ?string $nombre = null,
        ?Carbon $fecha = null,
    ): Presupuesto {
        $calculo = $this->calculo->para($proyecto);
        $fecha ??= Carbon::today();

        $precios = $this->normalizar($precios, $calculo, $monedaDestino);

        return DB::transaction(function () use ($proyecto, $calculo, $precios, $monedaDestino, $nombre, $fecha) {
            $presupuesto = Presupuesto::create([
                'user_id' => $proyecto->user_id,
                'proyecto_id' => $proyecto->id,
                'nombre' => $nombre ?? $proyecto->nombre,
                'moneda_destino' => $monedaDestino,
                'fecha' => $fecha,
                'estado' => Presupuesto::BORRADOR,
                'total' => 0,
            ]);

            foreach ($calculo->materiales as $orden => $material) {
                ['precio' => $precio, 'moneda' => $monedaOrigen] = $precios[$material->clave];

                $tasa = $this->tasas->entre($monedaOrigen, $monedaDestino, $fecha);

                PresupuestoLinea::create([
                    'presupuesto_id' => $presupuesto->id,
                    'material_id' => $material->materialId,
                    'orden' => $orden + 1,
                    'origen' => $material->origen,
                    'etapa' => $material->etapa,
                    'magnitud' => $material->magnitud,
                    'unidad_magnitud' => $material->unidadMagnitud,
                    'merma_pct' => $material->mermaPct,
                    'nombre_material' => $material->nombre,
                    'unidad_venta' => $material->unidadVenta,
                    'rendimiento' => $material->cantidad > 0 ? $material->magnitud / $material->cantidad : 0,
                    'precio_unitario' => $precio,
                    'moneda_origen' => $monedaOrigen,
                    'tasa_cambio' => $tasa,
                    'cantidad' => $material->cantidad,
                    'cantidad_comprar' => $material->cantidadComprar,
                    'detalle' => $material->detalle,
                    'subtotal' => PresupuestoLinea::subtotalDe(
                        $material->cantidadComprar,
                        $precio,
                        $tasa,
                    ),
                ]);
            }

            $presupuesto->load(['lineas', 'moneda']);
            $presupuesto->recalcularTotal();

            return $presupuesto->fresh(['lineas']);
        });
    }

    /**
     * Acepta un precio suelto o un precio con su moneda, y comprueba que estén
     * todos.
     *
     * Es estricto con las claves sobrantes a proposito: una clave que ya no
     * corresponde significa que el formulario se armo contra una version anterior
     * del proyecto, y seguir adelante produciria un presupuesto que no describe lo
     * que el usuario tiene en pantalla.
     *
     * @param  array<string, mixed>  $precios
     * @return array<string, array{precio: float, moneda: string}>
     */
    private function normalizar(array $precios, CalculoProyecto $calculo, string $monedaDestino): array
    {
        $esperadas = $calculo->claves();

        $faltantes = array_diff($esperadas, array_keys($precios));

        if ($faltantes !== []) {
            throw new UnprocessableEntityHttpException(
                'Faltan precios para: '.implode(', ', array_map(
                    fn (string $clave) => $calculo->material($clave)->nombre ?? $clave,
                    $faltantes,
                ))
            );
        }

        $sobrantes = array_diff(array_keys($precios), $esperadas);

        if ($sobrantes !== []) {
            throw new UnprocessableEntityHttpException(
                'Hay precios que no corresponden a ningun material del proyecto ('
                .implode(', ', $sobrantes).'): el formulario quedo desactualizado.'
            );
        }

        $normalizados = [];

        foreach ($precios as $clave => $valor) {
            $precio = is_array($valor) ? ($valor['precio'] ?? null) : $valor;
            $moneda = is_array($valor) ? ($valor['moneda'] ?? $monedaDestino) : $monedaDestino;

            if (! is_numeric($precio)) {
                throw new UnprocessableEntityHttpException(
                    'El precio de '.($calculo->material($clave)->nombre ?? $clave).' no es un numero.'
                );
            }

            if ((float) $precio < 0) {
                throw new UnprocessableEntityHttpException(
                    'El precio de '.($calculo->material($clave)->nombre ?? $clave).' no puede ser negativo.'
                );
            }

            $normalizados[$clave] = ['precio' => (float) $precio, 'moneda' => (string) $moneda];
        }

        return $normalizados;
    }
}
