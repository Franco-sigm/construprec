<?php

namespace App\Services\Tabiqueria;

use App\Support\Medida;

/**
 * Una tira comercial con los cortes que se sacan de ella.
 *
 * Es el plan de corte, no solo un conteo: sirve para que el usuario pueda
 * llevarse a la obra que sacar de cada tira, y para auditar por que el
 * presupuesto pide 31 tiras y no 28.
 */
final readonly class Tira
{
    /** @param  list<Pieza>  $cortes */
    public function __construct(
        public array $cortes,
        public Medida $largoComercial,
        public int $anchoCorteMm = 0,
    ) {}

    /**
     * Lo que consume la tira: los cortes mas lo que se come la sierra.
     *
     * Cada pasada convierte unos milimetros de madera en aserrin, asi que sacar
     * tres piezas de una tira gasta tres anchos de corte ademas de los tres
     * largos. Es poco —con 3 mm de disco, tres cortes son 9 mm— pero es la
     * diferencia entre que la ultima pieza quepa o no quepa, y quedarse corto en
     * obra cuesta un viaje a la barraca.
     */
    public function ocupado(): Medida
    {
        return array_reduce(
            $this->cortes,
            fn (Medida $suma, Pieza $c) => $suma
                ->mas($c->largo->por($c->cantidad))
                ->mas(Medida::desdeMm($this->anchoCorteMm)->por($c->cantidad)),
            Medida::cero(),
        );
    }

    /** Cuantos cortes de sierra exige esta tira. */
    public function cortesDeSierra(): int
    {
        return array_sum(array_map(fn (Pieza $c) => $c->cantidad, $this->cortes));
    }

    /** Madera convertida en aserrin en esta tira. */
    public function aserrinMm(): int
    {
        return $this->cortesDeSierra() * $this->anchoCorteMm;
    }

    /** Lo que sobra de la tira. Es desperdicio salvo que alcance para otro corte. */
    public function sobrante(): Medida
    {
        return $this->largoComercial->menos($this->ocupado());
    }

    public function cabe(Medida $largo): bool
    {
        return $this->sobrante()->mm >= $largo->mm;
    }

    public function con(Pieza $corte): self
    {
        return new self([...$this->cortes, $corte], $this->largoComercial, $this->anchoCorteMm);
    }

    /** @return array<string, mixed> */
    public function detalle(): array
    {
        return [
            'largo_comercial_mm' => $this->largoComercial->mm,
            'sobrante_mm' => $this->sobrante()->mm,
            'aserrin_mm' => $this->aserrinMm(),
            'cortes' => array_map(fn (Pieza $c) => [
                'rol' => $c->rol->value,
                'etiqueta' => $c->rol->etiqueta(),
                'largo_mm' => $c->largo->mm,
                'cantidad' => $c->cantidad,
            ], $this->cortes),
        ];
    }
}
