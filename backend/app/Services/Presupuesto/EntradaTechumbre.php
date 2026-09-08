<?php

namespace App\Services\Presupuesto;

use App\Services\Capas\Capa;
use App\Services\Techumbre\ConfiguracionTechumbre;

/**
 * Todo lo que hace falta para calcular la techumbre, en un solo objeto.
 *
 * Va agrupado y no como cuatro parametros sueltos porque la calculadora ya
 * recibe seis para los muros: sumarle cuatro mas la volveria imposible de leer
 * y de llamar sin equivocarse de orden.
 */
final readonly class EntradaTechumbre
{
    /**
     * @param  list<Capa>  $capas
     * @param  list<string>  $claves  clave de cada capa, en el mismo orden
     */
    public function __construct(
        public ConfiguracionTechumbre $config,
        public array $capas = [],
        public array $claves = [],
        public string $nombreMadera = 'Madera de techumbre',
    ) {}
}
