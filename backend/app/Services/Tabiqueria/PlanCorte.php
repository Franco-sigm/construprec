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
     * Lo que hay que comprar, con el descarte aplicado al total.
     *
     * `mermaPct` ya NO cubre el recorte del despiece ni el ancho de la sierra:
     * los dos se calculan. El recorte sale del empaquetado, que sabe exactamente
     * cuanto sobra de cada tira, y el aserrin sale de contar los cortes. Lo unico
     * que queda para el criterio de quien cotiza es el descarte por defectos:
     * piezas que llegan con nudos, torcidas o rajadas y hay que apartar. Eso
     * depende del grado de la madera y de la barraca, no de la geometria, asi que
     * ningun calculo lo puede deducir.
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

    /** Cortes de sierra que exige el plan. */
    public function cortesDeSierra(): int
    {
        return array_sum(array_map(fn (Tira $t) => $t->cortesDeSierra(), $this->tiras));
    }

    /** Madera convertida en aserrin, en metros. */
    public function aserrinM(): float
    {
        return round(array_sum(array_map(fn (Tira $t) => $t->aserrinMm(), $this->tiras)) / 1000, 4);
    }

    /**
     * Que porcentaje del material comprado se pierde, ya calculado.
     *
     * Es el numero que antes se pedia a ojo. Sirve para contrastar: si alguien
     * pone 5% de descarte y el recorte real ya va en 15%, conviene revisar el
     * largo comercial elegido antes que subir el porcentaje.
     */
    public function perdidaCalculadaPct(): float
    {
        $comprado = $this->metrosComprados();

        return $comprado > 0
            ? round(100 * ($this->desperdicioM() + $this->aserrinM()) / $comprado, 2)
            : 0.0;
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
            'cortes_de_sierra' => $this->cortesDeSierra(),
            'aserrin_m' => $this->aserrinM(),
            'perdida_calculada_pct' => $this->perdidaCalculadaPct(),
            'plan' => array_map(fn (Tira $t) => $t->detalle(), $this->tiras),
        ];
    }
}
