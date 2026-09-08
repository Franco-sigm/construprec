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

    private function techumbre(Proyecto $proyecto): ?EntradaTechumbre
    {
        $techo = $proyecto->techumbre;

        if ($techo === null) {
            return null;
        }

        $capas = $proyecto->capas->where('etapa', 'techumbre');
        $escuadria = $techo->escuadria;

        return new EntradaTechumbre(
            config: $techo->aDominio(),
            capas: $capas->map(fn (ProyectoCapa $c) => $c->aDominio())->values()->all(),
            claves: $capas->map(fn (ProyectoCapa $c) => 'techo_'.$c->id)->values()->all(),
            nombreMadera: sprintf(
                'Pino %s %s m (techumbre)',
                $escuadria?->descripcion() ?? 'de techumbre',
                number_format($techo->largo_comercial_mm / 1000, 2, ',', '.'),
            ),
        );
    }

    public function para(Proyecto $proyecto): CalculoProyecto
    {
        $proyecto->loadMissing([
            'caras.vanos', 'tabiqueria.escuadria', 'tabiqueria.escuadriaDintel',
            'capas', 'techumbre.escuadria', 'techumbre.escuadriaCostanera',
        ]);

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

        $murales = $proyecto->capas->where('etapa', 'muros');

        $escuadria = $tabiqueria->escuadria;
        $largoM = $tabiqueria->largo_comercial_mm / 1000;

        return $this->calculadora->calcular(
            caras: $caras,
            config: $tabiqueria->aDominio(),
            // Sólo las capas del muro: la cubierta va sobre el faldón, que mide
            // más que la planta, y cotizarla acá la calcularía sobre los metros
            // equivocados.
            capas: $murales->map(fn (ProyectoCapa $c) => $c->aDominio())->values()->all(),
            nombreMadera: $escuadria !== null
                ? sprintf('Pino %s %s m', $escuadria->descripcion(), number_format($largoM, 2, ',', '.'))
                : sprintf('Madera de tabiquería %s m', number_format($largoM, 2, ',', '.')),
            // La clave lleva el id de la capa y no su posición: un proyecto puede
            // tener dos capas del mismo tipo y el formulario de precios tiene que
            // poder distinguirlas.
            clavesCapa: $murales->map(fn (ProyectoCapa $c) => 'capa_'.$c->id)->values()->all(),
            materialIds: $murales->map(fn (ProyectoCapa $c) => $c->material_id)->values()->all(),
            // Las caras de un proyecto salen de una planta, así que forman un
            // contorno cerrado y hay tantos encuentros como muros.
            esquinas: $proyecto->caras->count(),
            techumbre: $this->techumbre($proyecto),
        );
    }
}
