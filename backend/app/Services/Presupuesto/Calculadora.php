<?php

namespace App\Services\Presupuesto;

use App\Models\PresupuestoLinea;
use App\Services\Capas\Capa;
use App\Services\Capas\ConsumoCapa;
use App\Services\Capas\ConsumoCapaService;
use App\Services\Madera\Despiece;
use App\Services\Madera\Pieza;
use App\Services\Madera\PlanCorte;
use App\Services\Madera\PlanCorteService;
use App\Services\Madera\RolPieza;
use App\Services\Tabiqueria\ConfiguracionTabique;
use App\Services\Tabiqueria\DespieceService;
use App\Support\Medida;
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
     * @param  int  $esquinas  encuentros entre muros. Cero si las caras no forman contorno cerrado.
     */
    public function calcular(
        array $caras,
        ConfiguracionTabique $config,
        array $capas = [],
        string $nombreMadera = 'Madera de tabiquería',
        array $clavesCapa = [],
        array $materialIds = [],
        int $esquinas = 0,
    ): CalculoProyecto {
        if ($caras === []) {
            throw new UnprocessableEntityHttpException('No hay ninguna cara que calcular.');
        }

        $despiece = Despiece::combinar(...array_map(
            fn (Cara $cara) => $this->despiece->deCara($cara->largo, $cara->alto, $cara->vanos, $config),
            $caras,
        ));

        $despiece = $this->conPostesDeEsquina($despiece, $caras, $config, $esquinas);

        $plan = $this->planCorte->para($despiece->piezas, $config->parametrosCorte());

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
     * Agrega los refuerzos de esquina al despiece ya combinado.
     *
     * Va acá y no en el despiece de cada cara porque la esquina es de las dos:
     * contarla dentro de `deCara` la cotizaría dos veces, una por cada muro que
     * llega al encuentro.
     *
     * @param  list<Cara>  $caras
     */
    private function conPostesDeEsquina(
        Despiece $despiece,
        array $caras,
        ConfiguracionTabique $config,
        int $esquinas,
    ): Despiece {
        $extra = $config->piezasExtraPorEsquina();

        if ($esquinas < 1 || $extra < 1) {
            return $despiece;
        }

        // La esquina une dos caras que pueden tener distinto alto. Se toma la más
        // alta: una pieza corta no llega arriba, y recortar es trivial mientras
        // que alargar no se puede.
        $alto = $config->altoPieDerecho(
            array_reduce(
                $caras,
                fn (?Medida $mayor, Cara $c) => $mayor === null || $c->alto->mayorQue($mayor) ? $c->alto : $mayor,
                null,
            ),
        );

        return Despiece::combinar(
            $despiece,
            new Despiece([new Pieza(RolPieza::PosteEsquina, $alto, $esquinas * $extra)], 0.0, 0.0, 0.0),
        );
    }

    /**
     * La madera es un solo material aunque de la escuadría salgan siete roles:
     * en la barraca se compra un producto y se paga un precio.
     */
    private function madera(PlanCorte $plan, Despiece $despiece, string $nombre): MaterialRequerido
    {
        return new MaterialRequerido(
            clave: 'madera',
            nombre: $nombre,
            // Sólo "tira": el largo ya va en el nombre del material, y dejarlo
            // también acá daba "80 tira 3.20 m", que no concuerda en plural ni se
            // puede arreglar sin inventar reglas de gramática.
            unidadVenta: 'tira',
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
