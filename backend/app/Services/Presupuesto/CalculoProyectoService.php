<?php

namespace App\Services\Presupuesto;

use App\Models\PresupuestoLinea;
use App\Models\Proyecto;
use App\Models\ProyectoCapa;
use App\Models\ProyectoCara;
use App\Services\Capas\ConsumoCapa;
use App\Services\Capas\ConsumoCapaService;
use App\Services\Tabiqueria\Despiece;
use App\Services\Tabiqueria\DespieceService;
use App\Services\Tabiqueria\PlanCorte;
use App\Services\Tabiqueria\PlanCorteService;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Deduce que materiales necesita un proyecto y cuantos, sin preguntar precios.
 *
 * Es el paso que convierte geometria en lista de compra. El usuario no arma
 * nunca la lista: entrega las medidas y esto decide que hay que comprar.
 */
final class CalculoProyectoService
{
    public function __construct(
        private readonly DespieceService $despiece,
        private readonly PlanCorteService $planCorte,
        private readonly ConsumoCapaService $consumoCapas,
    ) {}

    public function para(Proyecto $proyecto): CalculoProyecto
    {
        $proyecto->loadMissing(['caras.vanos', 'tabiqueria.escuadria', 'tabiqueria.escuadriaDintel', 'capas']);

        $tabiqueria = $proyecto->tabiqueria;

        if ($tabiqueria === null) {
            throw new UnprocessableEntityHttpException(
                'El proyecto no tiene configurada la tabiqueria: falta la escuadria y la separacion.'
            );
        }

        if ($proyecto->caras->isEmpty()) {
            throw new UnprocessableEntityHttpException('El proyecto no tiene ninguna cara que calcular.');
        }

        $config = $tabiqueria->aDominio();

        $despieces = $proyecto->caras
            ->map(fn (ProyectoCara $cara) => $this->despiece->deCara(
                $cara->largo(),
                $cara->alto(),
                $cara->vanosDominio(),
                $config,
            ))
            ->all();

        $despiece = Despiece::combinar(...$despieces);
        $plan = $this->planCorte->para($despiece->piezas, $config);

        $materiales = [
            $this->materialDeMadera($proyecto, $plan, $despiece),
            ...$this->materialesDeCapas($proyecto, $despiece),
        ];

        return new CalculoProyecto($despiece, $plan, $materiales);
    }

    /**
     * La madera es un solo material aunque de ella salgan siete roles distintos:
     * en la barraca se compra un producto y se paga un precio.
     */
    private function materialDeMadera(
        Proyecto $proyecto,
        PlanCorte $plan,
        Despiece $despiece,
    ): MaterialRequerido {
        $escuadria = $proyecto->tabiqueria?->escuadria;
        $largoM = $plan->largoComercial->metros();

        $nombre = $escuadria !== null
            ? sprintf('Pino %s %.2f m', $escuadria->descripcion(), $largoM)
            : sprintf('Madera de tabiqueria %.2f m', $largoM);

        return new MaterialRequerido(
            clave: 'madera',
            nombre: $nombre,
            unidadVenta: sprintf('tira %.2f m', $largoM),
            magnitud: $despiece->metrosLinealesTotales(),
            unidadMagnitud: 'ml',
            cantidad: (float) $plan->tirasNetas(),
            cantidadComprar: (float) $plan->tirasAComprar(),
            origen: PresupuestoLinea::ORIGEN_ESTRUCTURA,
            mermaPct: $plan->mermaPct,
            detalle: [...$despiece->detalle(), 'corte' => $plan->detalle()],
        );
    }

    /**
     * @return list<MaterialRequerido>
     */
    private function materialesDeCapas(Proyecto $proyecto, Despiece $despiece): array
    {
        $materiales = [];

        foreach ($proyecto->capas as $capa) {
            /** @var ProyectoCapa $capa */
            $consumo = $this->consumoCapas->para(
                $capa->aDominio(),
                $despiece->superficieBrutaM2,
                $despiece->superficieNetaM2(),
            );

            $materiales[] = $this->deConsumo($capa, $consumo);
        }

        return $materiales;
    }

    private function deConsumo(ProyectoCapa $capa, ConsumoCapa $consumo): MaterialRequerido
    {
        return new MaterialRequerido(
            // La clave lleva el id de la capa y no solo el tipo: un proyecto puede
            // tener dos capas del mismo tipo, por ejemplo una barrera bajo el
            // siding mas el siding, y el formulario tiene que poder distinguirlas.
            clave: 'capa_'.$capa->id,
            nombre: $capa->nombre,
            unidadVenta: $capa->unidad_venta,
            magnitud: $consumo->superficieBaseM2,
            unidadMagnitud: 'm2',
            cantidad: $consumo->cantidad,
            cantidadComprar: $consumo->cantidadComprar,
            origen: PresupuestoLinea::ORIGEN_CAPA,
            mermaPct: (float) $capa->merma_pct,
            materialId: $capa->material_id,
            detalle: $consumo->detalle(),
        );
    }
}
