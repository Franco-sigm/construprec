<?php

namespace App\Services\Tabiqueria;

use App\Support\Medida;
use InvalidArgumentException;

/**
 * Un grupo de piezas iguales: mismo rol y mismo largo de corte.
 *
 * Se agrupan y no se listan una por una porque el despiece que sigue corta por
 * largo: para saber cuantas tiras comerciales hacen falta, lo que importa es
 * cuantos cortes de 2,32 m se necesitan, no cual de ellos es el tercer pie
 * derecho de la cara norte.
 */
final readonly class Pieza
{
    public function __construct(
        public RolPieza $rol,
        public Medida $largo,
        public int $cantidad,
    ) {
        if ($cantidad < 0) {
            throw new InvalidArgumentException('La cantidad de piezas no puede ser negativa.');
        }
    }

    /** Metros lineales totales de este grupo. */
    public function metrosLineales(): float
    {
        return round($this->largo->metros() * $this->cantidad, 4);
    }

    public function conCantidad(int $cantidad): self
    {
        return new self($this->rol, $this->largo, $cantidad);
    }
}
