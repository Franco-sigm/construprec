<?php

namespace App\Services\Tabiqueria;

use App\Support\Medida;

/**
 * Cuantas tiras comerciales hay que comprar y como se cortan.
 *
 * Separa dos cuentas que no se pueden sumar antes de tiempo:
 *
 *   `tiras` son las piezas que salen enteras de una tira (pies derechos,
 *   jambas, dinteles). Se resuelven empaquetando cortes en tiras.
 *
 *   `tirasCorridas` son las soleras, que admiten empalme sobre un pie derecho.
 *   Ahi solo importan los metros lineales totales, no de que largo es cada
 *   tramo.
 */
final readonly class PlanCorte
{
    /** @param  list<Tira>  $tiras */
    public function __construct(
        public array $tiras,
        public int $tirasCorridas,
        public float $metrosCorridas,
        public Medida $largoComercial,
        public float $mermaPct,
    ) {}

    /** Tiras antes de aplicar merma. */
    public function tirasNetas(): int
    {
        return count($this->tiras) + $this->tirasCorridas;
    }

    /**
     * Lo que hay que comprar, con la merma aplicada al total.
     *
     * La merma se aplica sobre el total y no sobre cada corte porque cubre lo que
     * el plan de corte no modela: el ancho que se come la sierra en cada pasada,
     * los cortes que salen mal y las piezas que llegan con nudos o torceduras y
     * hay que descartar.
     */
    public function tirasAComprar(): int
    {
        return (int) ceil($this->tirasNetas() * (1 + $this->mermaPct / 100));
    }

    /** Metros que quedan como recorte inutilizable en las tiras empaquetadas. */
    public function desperdicioM(): float
    {
        return round(array_sum(array_map(fn (Tira $t) => $t->sobrante()->metros(), $this->tiras)), 4);
    }

    public function metrosComprados(): float
    {
        return round($this->tirasAComprar() * $this->largoComercial->metros(), 4);
    }

    /** @return array<string, mixed> */
    public function detalle(): array
    {
        return [
            'largo_comercial_mm' => $this->largoComercial->mm,
            'tiras_piezas' => count($this->tiras),
            'tiras_corridas' => $this->tirasCorridas,
            'metros_corridas' => $this->metrosCorridas,
            'merma_pct' => $this->mermaPct,
            'tiras_netas' => $this->tirasNetas(),
            'tiras_a_comprar' => $this->tirasAComprar(),
            'desperdicio_m' => $this->desperdicioM(),
            'plan' => array_map(fn (Tira $t) => $t->detalle(), $this->tiras),
        ];
    }
}
