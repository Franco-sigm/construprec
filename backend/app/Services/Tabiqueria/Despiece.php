<?php

namespace App\Services\Tabiqueria;

use App\Support\Medida;

/**
 * Resultado del despiece de una cara: que piezas hay que cortar y cuanta
 * superficie queda para los recubrimientos.
 */
final readonly class Despiece
{
    /** @param  list<Pieza>  $piezas */
    public function __construct(
        public array $piezas,
        public float $superficieBrutaM2,
        public float $superficieVanosM2,
        public float $areaEstructuraM2 = 0.0,
    ) {}

    /** Superficie que efectivamente se recubre: la del muro menos los huecos. */
    public function superficieNetaM2(): float
    {
        return round(max(0.0, $this->superficieBrutaM2 - $this->superficieVanosM2), 4);
    }

    /**
     * Superficie de cavidad: lo que queda del muro despues de descontar los vanos
     * y la madera de la estructura.
     *
     * Es dato informativo y no entra en el calculo de ninguna capa. El aislante se
     * cotiza igual sobre la superficie neta, porque el 19% que ocupa la madera se
     * compensa con lo que se pierde al cortar el rollo en tiras del ancho de la
     * cavidad: descontar solo la madera y olvidar el corte deja la obra corta, que
     * es el error caro. Se expone para que se pueda ver el numero y decidir en un
     * proyecto puntual si conviene apretar la merma.
     */
    public function cavidadM2(): float
    {
        return round(max(0.0, $this->superficieNetaM2() - $this->areaEstructuraM2), 4);
    }

    /** @return list<Pieza> */
    public function porRol(RolPieza $rol): array
    {
        return array_values(array_filter($this->piezas, fn (Pieza $p) => $p->rol === $rol));
    }

    public function cantidadDe(RolPieza $rol): int
    {
        return array_sum(array_map(fn (Pieza $p) => $p->cantidad, $this->porRol($rol)));
    }

    public function metrosLinealesDe(RolPieza $rol): float
    {
        return round(array_sum(array_map(fn (Pieza $p) => $p->metrosLineales(), $this->porRol($rol))), 4);
    }

    public function metrosLinealesTotales(): float
    {
        return round(array_sum(array_map(fn (Pieza $p) => $p->metrosLineales(), $this->piezas)), 4);
    }

    /**
     * Junta piezas del mismo rol y largo que vinieron de caras distintas.
     * Sin esto, cuatro caras iguales producen cuatro grupos identicos y la lista
     * de compra se vuelve ilegible.
     */
    public static function combinar(self ...$despieces): self
    {
        /** @var array<string, Pieza> $agrupadas */
        $agrupadas = [];
        $bruta = 0.0;
        $vanos = 0.0;
        $estructura = 0.0;

        foreach ($despieces as $despiece) {
            foreach ($despiece->piezas as $pieza) {
                $clave = $pieza->rol->value.'|'.$pieza->largo->mm;

                $agrupadas[$clave] = isset($agrupadas[$clave])
                    ? $agrupadas[$clave]->conCantidad($agrupadas[$clave]->cantidad + $pieza->cantidad)
                    : $pieza;
            }

            $bruta += $despiece->superficieBrutaM2;
            $vanos += $despiece->superficieVanosM2;
            $estructura += $despiece->areaEstructuraM2;
        }

        // Orden estable: por rol y de mayor a menor largo. Que la lista no cambie
        // de orden entre corridas hace que un presupuesto se pueda comparar contra
        // el anterior.
        $piezas = array_values($agrupadas);
        usort($piezas, fn (Pieza $a, Pieza $b) => [$a->rol->value, -$a->largo->mm] <=> [$b->rol->value, -$b->largo->mm]);

        return new self($piezas, round($bruta, 4), round($vanos, 4), round($estructura, 4));
    }

    public static function vacio(): self
    {
        return new self([], 0.0, 0.0);
    }

    /** @return array<string, mixed> Desglose auditable, para la columna `detalle` de la linea. */
    public function detalle(): array
    {
        return [
            'superficie_bruta_m2' => $this->superficieBrutaM2,
            'superficie_vanos_m2' => $this->superficieVanosM2,
            'superficie_neta_m2' => $this->superficieNetaM2(),
            'area_estructura_m2' => $this->areaEstructuraM2,
            'cavidad_m2' => $this->cavidadM2(),
            'piezas' => array_map(fn (Pieza $p) => [
                'rol' => $p->rol->value,
                'etiqueta' => $p->rol->etiqueta(),
                'largo_mm' => $p->largo->mm,
                'cantidad' => $p->cantidad,
                'metros_lineales' => $p->metrosLineales(),
            ], $this->piezas),
        ];
    }

    /**
     * Helper interno para construir el resultado de una cara.
     *
     * El area de estructura se calcula con el espesor de la pieza para todos los
     * roles, incluido el dintel, que en rigor va de canto y ocupa algo mas. La
     * diferencia es cercana al 1% de un dato que de todos modos es informativo, y
     * no justifica que este objeto tenga que conocer la escuadria del dintel.
     *
     * @param  list<Pieza>  $piezas
     */
    public static function deCara(array $piezas, Medida $largo, Medida $alto, float $vanosM2, Medida $espesorPieza): self
    {
        $estructura = array_sum(array_map(
            fn (Pieza $p) => $p->metrosLineales() * $espesorPieza->metros(),
            $piezas,
        ));

        return new self($piezas, $largo->porM2($alto), round($vanosM2, 4), round($estructura, 4));
    }
}
