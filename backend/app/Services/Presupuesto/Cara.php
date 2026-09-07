<?php

namespace App\Services\Presupuesto;

use App\Services\Tabiqueria\Vano;
use App\Support\Medida;

/**
 * Una cara a calcular, ya despegada de Eloquent.
 *
 * Existe para que el motor pueda trabajar sobre un proyecto que todavía no se
 * guardó. El asistente recalcula cada vez que el usuario mueve la separación o
 * cambia un producto, y crear filas en la base para cada una de esas pulsaciones
 * llenaría la tabla de proyectos que nadie pidió.
 */
final readonly class Cara
{
    /** @param  list<Vano>  $vanos */
    public function __construct(
        public Medida $largo,
        public Medida $alto,
        public array $vanos = [],
        public string $nombre = '',
        public bool $esExterior = true,
    ) {}
}
