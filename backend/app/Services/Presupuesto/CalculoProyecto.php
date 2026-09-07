<?php

namespace App\Services\Presupuesto;

use App\Services\Madera\Despiece;
use App\Services\Madera\PlanCorte;

/**
 * Todo lo que se puede saber de un proyecto antes de conocer un solo precio.
 */
final readonly class CalculoProyecto
{
    /** @param  list<MaterialRequerido>  $materiales */
    public function __construct(
        public Despiece $despiece,
        public PlanCorte $planCorte,
        public array $materiales,
    ) {}

    public function material(string $clave): ?MaterialRequerido
    {
        foreach ($this->materiales as $material) {
            if ($material->clave === $clave) {
                return $material;
            }
        }

        return null;
    }

    /**
     * Las claves que el formulario de precios tiene que devolver.
     *
     * @return list<string>
     */
    public function claves(): array
    {
        return array_map(fn (MaterialRequerido $m) => $m->clave, $this->materiales);
    }

    /** @return list<array<string, mixed>> */
    public function paraFormulario(): array
    {
        return array_map(fn (MaterialRequerido $m) => $m->paraFormulario(), $this->materiales);
    }
}
