<?php

namespace App\Services\Madera;

use App\Support\Medida;
use InvalidArgumentException;

/**
 * Lo que el plan de corte necesita saber, y nada más.
 *
 * Antes recibía la configuración completa del tabique, aunque de ella sólo leía
 * tres cosas. Eso obligaba a que cualquier otra parte de la obra —una techumbre,
 * un entrepiso— tuviera que fingir que era un muro para poder cortar su madera.
 * Cortar tiras es la misma operación en todas: hay un largo comercial, un disco
 * que se come milímetros y un porcentaje de piezas que se descartan.
 */
final readonly class ParametrosCorte
{
    public function __construct(
        public Medida $largoComercial,
        public float $mermaPct = 0.0,
        public int $anchoCorteMm = 3,
    ) {
        if ($largoComercial->esCero()) {
            throw new InvalidArgumentException('El largo comercial no puede ser cero.');
        }

        if ($mermaPct < 0) {
            throw new InvalidArgumentException('El descarte no puede ser negativo.');
        }

        if ($anchoCorteMm < 0) {
            throw new InvalidArgumentException('El ancho de corte no puede ser negativo.');
        }
    }
}
