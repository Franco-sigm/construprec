<?php

namespace App\Services\Presupuesto;

use App\Models\PresupuestoLinea;
use App\Services\Capas\Capa;
use App\Services\Capas\ConsumoCapa;
use App\Services\Capas\ConsumoCapaService;
use App\Services\Tabiqueria\ConfiguracionTabique;
use App\Services\Tabiqueria\Despiece;
use App\Services\Tabiqueria\DespieceService;
use App\Services\Tabiqueria\PlanCorte;
use App\Services\Tabiqueria\PlanCorteService;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * El motor: geometría más configuración, y devuelve qué comprar.
 *
 * No conoce Eloquent. Recibe objetos de valor y devuelve objetos de valor, así
 * que sirve igual para un proyecto guardado en la base y para uno que el usuario
 * todavía está armando en pantalla.
 */
final class Calculadora
{
    public function __construct(
        private readonly DespieceService $despiece,
        private readonly PlanCorteService $planCorte,
        private readonly ConsumoCapaService $consumoCapas,
    ) {}

    /**
     * @param  list<Cara>  $caras
     * @param  list<Capa>  $capas
     * @param  list<string>  $clavesCapa  clave de cada capa, en el mismo orden que $capas
     * @param  list<int|null>  $materialIds  material del catálogo de cada capa, si tenía uno
     */
    public function calcular(
        array $caras,
        ConfiguracionTabique $config,
        array $capas = [],
        string $nombreMadera = 'Madera de tabiquería',
        array $clavesCapa = [],
        array $materialIds = [],
    ): CalculoProyecto {
        if ($caras === []) {
            throw new UnprocessableEntityHttpException('No hay ninguna cara que calcular.');
        }

        $despiece = Despiece::combinar(...array_map(
            fn (Cara $cara) => $this->despiece->deCara($cara->largo, $cara->alto, $cara->vanos, $config),
            $caras,
        ));

        $plan = $this->planCorte->para($despiece->piezas, $config);

        $materiales = [$this->madera($plan, $despiece, $nombreMadera)];

        foreach ($capas as $i => $capa) {
            $materiales[] = $this->deCapa(
                $capa,
                $this->consumoCapas->para($capa, $despiece->superficieBrutaM2, $despiece->superficieNetaM2()),
                $clavesCapa[$i] ?? 'capa_'.($i + 1),
                $materialIds[$i] ?? null,
            );
        }

        return new CalculoProyecto($despiece, $plan, $materiales);
    }

    /**
     * La madera es un solo material aunque de la escuadría salgan siete roles:
     * en la barraca se compra un producto y se paga un precio.
     */
    private function madera(PlanCorte $plan, Despiece $despiece, string $nombre): MaterialRequerido
    {
        $largoM = $plan->largoComercial->metros();

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

    private function deCapa(Capa $capa, ConsumoCapa $consumo, string $clave, ?int $materialId): MaterialRequerido
    {
        return new MaterialRequerido(
            clave: $clave,
            nombre: $capa->nombre,
            unidadVenta: $capa->unidadVenta,
            magnitud: $consumo->superficieBaseM2,
            unidadMagnitud: 'm2',
            cantidad: $consumo->cantidad,
            cantidadComprar: $consumo->cantidadComprar,
            origen: PresupuestoLinea::ORIGEN_CAPA,
            mermaPct: $capa->mermaPct,
            materialId: $materialId,
            detalle: $consumo->detalle(),
        );
    }
}
