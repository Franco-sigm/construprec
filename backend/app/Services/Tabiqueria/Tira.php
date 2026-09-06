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
    ) {}

    public function ocupado(): Medida
    {
        return array_reduce(
            $this->cortes,
            fn (Medida $suma, Pieza $c) => $suma->mas($c->largo->por($c->cantidad)),
            Medida::cero(),
        );
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
        return new self([...$this->cortes, $corte], $this->largoComercial);
    }

    /** @return array<string, mixed> */
    public function detalle(): array
    {
        return [
            'largo_comercial_mm' => $this->largoComercial->mm,
            'sobrante_mm' => $this->sobrante()->mm,
            'cortes' => array_map(fn (Pieza $c) => [
                'rol' => $c->rol->value,
                'etiqueta' => $c->rol->etiqueta(),
                'largo_mm' => $c->largo->mm,
                'cantidad' => $c->cantidad,
            ], $this->cortes),
        ];
    }
}
