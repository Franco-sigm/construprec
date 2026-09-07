<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalcularRequest;
use App\Models\Escuadria;
use App\Models\ProductoCapa;
use App\Services\Capas\AplicacionCapa;
use App\Services\Capas\Capa;
use App\Services\Presupuesto\Calculadora;
use App\Services\Presupuesto\Cara;
use App\Services\Presupuesto\MaterialRequerido;
use App\Services\Tabiqueria\ConfiguracionTabique;
use App\Services\Tabiqueria\TipoVano;
use App\Services\Tabiqueria\Vano;
use App\Support\Medida;
use Illuminate\Http\JsonResponse;

/**
 * Calcula sin guardar nada.
 *
 * El asistente recalcula cada vez que el usuario mueve la separación o cambia un
 * producto. Persistir en cada una de esas pulsaciones llenaría la base de
 * proyectos que nadie pidió, así que el cálculo es una consulta pura y guardar es
 * un paso aparte que el usuario decide.
 */
class CalculoController extends Controller
{
    public function __construct(private readonly Calculadora $calculadora) {}

    public function __invoke(CalcularRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $escuadria = Escuadria::findOrFail($datos['tabiqueria']['escuadria_id']);
        $dintel = isset($datos['tabiqueria']['escuadria_dintel_id'])
            ? Escuadria::find($datos['tabiqueria']['escuadria_dintel_id'])
            : null;

        // Si no se eligió largo comercial se toma el más corto que ofrezca la
        // escuadría: es el que deja menos recorte.
        $largoComercial = $datos['tabiqueria']['largo_comercial_mm']
            ?? min($escuadria->largos_comerciales_mm);

        $config = new ConfiguracionTabique(
            escuadriaAncho: $escuadria->ancho(),
            escuadriaAlto: $escuadria->alto(),
            separacion: Medida::de(
                $datos['tabiqueria']['separacion'],
                $datos['tabiqueria']['separacion_unidad'] ?? 'm',
            ),
            largoComercial: Medida::desdeMm((int) $largoComercial),
            soleraInferior: $datos['tabiqueria']['solera_inferior'] ?? true,
            solerasSuperiores: $datos['tabiqueria']['soleras_superiores'] ?? 1,
            escuadriaDintelAlto: $dintel?->alto(),
            mermaPct: (float) ($datos['tabiqueria']['merma_pct'] ?? 0),
            filasCadenetas: $datos['tabiqueria']['filas_cadenetas'] ?? 1,
        );

        [$capas, $claves] = $this->capas($datos['capas'] ?? []);
        $caras = $this->caras($datos['caras']);

        $calculo = $this->calculadora->calcular(
            caras: $caras,
            config: $config,
            capas: $capas,
            nombreMadera: sprintf('Pino %s %.2f m', $escuadria->descripcion(), $largoComercial / 1000),
            clavesCapa: $claves,
        );

        return response()->json([
            'materiales' => array_map(fn (MaterialRequerido $m) => [
                'clave' => $m->clave,
                'nombre' => $m->nombre,
                'unidad_venta' => $m->unidadVenta,
                'cantidad' => $m->cantidad,
                'cantidad_comprar' => $m->cantidadComprar,
                'origen' => $m->origen,
                'merma_pct' => $m->mermaPct,
            ], $calculo->materiales),

            'obra' => [
                'superficie_bruta_m2' => $calculo->despiece->superficieBrutaM2,
                'superficie_vanos_m2' => $calculo->despiece->superficieVanosM2,
                'superficie_neta_m2' => $calculo->despiece->superficieNetaM2(),
                'area_estructura_m2' => $calculo->despiece->areaEstructuraM2,
                'cavidad_m2' => $calculo->despiece->cavidadM2(),
                'metros_lineales_madera' => $calculo->despiece->metrosLinealesTotales(),
                'piezas' => array_sum(array_map(fn ($p) => $p->cantidad, $calculo->despiece->piezas)),
            ],

            'corte' => $calculo->planCorte->detalle(),
            'despiece' => $calculo->despiece->detalle(),

            'tabiqueria' => [
                'separacion_mm' => $config->separacion->mm,
                'espesor_pieza_mm' => $config->escuadriaAncho->mm,
                'largo_comercial_mm' => $config->largoComercial->mm,
                // Anchos de vano que dejan las jambas sobre la trama de pies
                // derechos. Se mandan para que la interfaz pueda proponerlos sin
                // reimplementar la fórmula y arriesgarse a que se desincronicen.
                'anchos_modulares_mm' => array_map(
                    fn (Medida $m) => $m->mm,
                    $config->anchosModulares(),
                ),
            ],

            'caras' => $this->diagnosticoDeCaras($caras, $config),
        ]);
    }

    /**
     * Cómo se lleva cada vano con la trama de pies derechos.
     *
     * Se calcula acá y no en la interfaz para que la fórmula viva en un solo
     * lugar: si mañana cambia el modo de enmarcar un vano, no puede quedar una
     * copia en JavaScript diciendo otra cosa.
     *
     * @param  list<Cara>  $caras
     * @return list<array<string, mixed>>
     */
    private function diagnosticoDeCaras(array $caras, ConfiguracionTabique $config): array
    {
        return array_map(fn (Cara $cara) => [
            'nombre' => $cara->nombre,
            'largo_mm' => $cara->largo->mm,
            'alto_mm' => $cara->alto->mm,
            'superficie_m2' => $cara->largo->porM2($cara->alto),
            'vanos' => array_map(function (Vano $vano) use ($config) {
                $cercano = $config->anchoModularMasCercano($vano->ancho);

                return [
                    'tipo' => $vano->tipo->value,
                    'ancho_mm' => $vano->ancho->mm,
                    'alto_mm' => $vano->alto->mm,
                    'antepecho_mm' => $vano->antepecho->mm,
                    'cantidad' => $vano->cantidad,
                    'calza_con_la_trama' => $config->calzaConLaTrama($vano->ancho),
                    'tramos_que_ocupa' => $config->tramosQueOcupa($vano->ancho),
                    'ancho_sugerido_mm' => $cercano?->mm,
                ];
            }, $cara->vanos),
        ], $caras);
    }

    /**
     * @param  array<int, mixed>  $datos
     * @return list<Cara>
     */
    private function caras(array $datos): array
    {
        return array_map(function (array $cara) {
            $unidad = $cara['unidad'] ?? 'm';

            return new Cara(
                largo: Medida::de($cara['largo'], $unidad),
                alto: Medida::de($cara['alto'], $unidad),
                vanos: array_map(function (array $vano) use ($unidad) {
                    $u = $vano['unidad'] ?? $unidad;

                    return new Vano(
                        tipo: TipoVano::from($vano['tipo']),
                        ancho: Medida::de($vano['ancho'], $u),
                        alto: Medida::de($vano['alto'], $u),
                        antepecho: Medida::de($vano['antepecho'] ?? 0, $u),
                        cantidad: $vano['cantidad'] ?? 1,
                    );
                }, array_values($cara['vanos'] ?? [])),
                nombre: $cara['nombre'] ?? '',
                esExterior: $cara['es_exterior'] ?? true,
            );
        }, array_values($datos));
    }

    /**
     * @param  array<int, mixed>  $datos
     * @return array{list<Capa>, list<string>}
     */
    private function capas(array $datos): array
    {
        $capas = [];
        $claves = [];

        foreach (array_values($datos) as $i => $capa) {
            $producto = ProductoCapa::findOrFail($capa['producto_capa_id']);

            $capas[] = new Capa(
                tipo: $producto->tipo,
                nombre: $producto->nombre,
                unidadVenta: $producto->unidad_venta,
                rendimientoM2: $producto->rendimientoM2(),
                aplicacion: AplicacionCapa::from($capa['aplicacion'] ?? 'exterior'),
                fraccionable: $producto->fraccionable,
                mermaPct: (float) ($capa['merma_pct'] ?? $producto->merma_sugerida_pct),
                descuentaVanos: $capa['descuenta_vanos'] ?? null,
            );

            // La clave lleva el producto y la posición: dos capas del mismo
            // producto en aplicaciones distintas tienen que poder distinguirse en
            // el formulario de precios.
            $claves[] = 'capa_'.$i.'_'.$producto->id;
        }

        return [$capas, $claves];
    }
}
