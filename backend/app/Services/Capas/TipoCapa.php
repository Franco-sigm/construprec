<?php

namespace App\Services\Capas;

/**
 * Que funcion cumple la capa sobre el tabique.
 *
 * El tipo no dice que producto se usa: dos proyectos pueden arriostrar con OSB y
 * con terciado y el calculo es identico. Lo que decide el tipo es sobre que
 * superficie se aplica y si los vanos se descuentan o no.
 */
enum TipoCapa: string
{
    case Arriostramiento = 'arriostramiento';
    case RevestimientoInterior = 'rev_interior';
    case RevestimientoExterior = 'rev_exterior';
    case Aislante = 'aislante';
    case Membrana = 'membrana';

    /**
     * Si por defecto se descuenta la superficie de puertas y ventanas.
     *
     * No es lo mismo en todas las capas y la diferencia es plata:
     *
     * La membrana hidrofuga se despliega corrida sobre la fachada completa y se
     * recorta en los vanos despues de fijarla. El material que cubre el hueco se
     * compra igual, asi que descontarlo dejaria la obra corta.
     *
     * El aislante va dentro de la cavidad del tabique, donde el vano simplemente
     * no existe: ahi descontar es obligatorio o se cotiza lana para rellenar una
     * ventana.
     *
     * El resto se descuenta por defecto, pero es discutible segun como se corte
     * en obra, y por eso cada capa puede decidirlo aparte.
     */
    public function descuentaVanosPorDefecto(): bool
    {
        return match ($this) {
            self::Membrana => false,
            default => true,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Arriostramiento => 'Arriostramiento / tablero estructural',
            self::RevestimientoInterior => 'Revestimiento interior',
            self::RevestimientoExterior => 'Revestimiento exterior',
            self::Aislante => 'Aislante termico',
            self::Membrana => 'Membrana hidrofuga',
        };
    }
}
