<?php

namespace App\Services\Presupuesto;

use App\Models\Proyecto;
use App\Models\ProyectoCapa;
use App\Models\ProyectoCara;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Adapta un proyecto guardado al motor de cálculo.
 *
 * Toda la lógica vive en Calculadora, que no conoce Eloquent. Esta clase sólo
 * traduce filas a objetos de valor: así el mismo motor sirve para un proyecto de
 * la base y para uno que el usuario todavía está armando en pantalla, sin
 * duplicar una sola regla.
 */
final class CalculoProyectoService
{
    public function __construct(private readonly Calculadora $calculadora) {}

    public function para(Proyecto $proyecto): CalculoProyecto
    {
        $proyecto->loadMissing(['caras.vanos', 'tabiqueria.escuadria', 'tabiqueria.escuadriaDintel', 'capas']);

        $tabiqueria = $proyecto->tabiqueria;

        if ($tabiqueria === null) {
            throw new UnprocessableEntityHttpException(
                'El proyecto no tiene configurada la tabiqueria: falta la escuadria y la separacion.'
            );
        }

        if ($proyecto->caras->isEmpty()) {
            throw new UnprocessableEntityHttpException('El proyecto no tiene ninguna cara que calcular.');
        }

        $caras = $proyecto->caras
            ->map(fn (ProyectoCara $cara) => new Cara(
                largo: $cara->largo(),
                alto: $cara->alto(),
                vanos: $cara->vanosDominio(),
                nombre: $cara->nombre,
                esExterior: $cara->es_exterior,
            ))
            ->all();

        $escuadria = $tabiqueria->escuadria;
        $largoM = $tabiqueria->largo_comercial_mm / 1000;

        return $this->calculadora->calcular(
            caras: $caras,
            config: $tabiqueria->aDominio(),
            capas: $proyecto->capas->map(fn (ProyectoCapa $c) => $c->aDominio())->all(),
            nombreMadera: $escuadria !== null
                ? sprintf('Pino %s %.2f m', $escuadria->descripcion(), $largoM)
                : sprintf('Madera de tabiquería %.2f m', $largoM),
            // La clave lleva el id de la capa y no su posición: un proyecto puede
            // tener dos capas del mismo tipo y el formulario de precios tiene que
            // poder distinguirlas.
            clavesCapa: $proyecto->capas->map(fn (ProyectoCapa $c) => 'capa_'.$c->id)->all(),
            materialIds: $proyecto->capas->map(fn (ProyectoCapa $c) => $c->material_id)->all(),
            // Las caras de un proyecto salen de una planta, así que forman un
            // contorno cerrado y hay tantos encuentros como muros.
            esquinas: $proyecto->caras->count(),
        );
    }
}
