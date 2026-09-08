<?php

namespace App\Services\Presupuesto;

use App\Services\Madera\Despiece;
use App\Services\Madera\PlanCorte;

/**
 * Todo lo que se puede saber de un proyecto antes de conocer un solo precio.
 */
final readonly class CalculoProyecto
{
    /**
     * `despiece` y `planCorte` son los de los muros; los de la techumbre van
     * aparte porque se cortan de tiras distintas y con otra merma, y sumarlos
     * daría un plan de corte que no se puede seguir en obra.
     *
     * `materiales` sí los trae todos juntos, cada uno con su etapa: es la lista de
     * compra, y en la barraca se compra una sola vez.
     *
     * @param  list<MaterialRequerido>  $materiales
     */
    public function __construct(
        public Despiece $despiece,
        public PlanCorte $planCorte,
        public array $materiales,
        public ?Despiece $despieceTechumbre = null,
        public ?PlanCorte $planCorteTechumbre = null,
    ) {}

    public function tieneTechumbre(): bool
    {
        return $this->despieceTechumbre !== null;
    }

    /** @return list<MaterialRequerido> */
    public function deEtapa(string $etapa): array
    {
        return array_values(array_filter($this->materiales, fn (MaterialRequerido $m) => $m->etapa === $etapa));
    }

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
