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
     * Lo que recibe el agua: zinc, teja, panel.
     *
     * Va sobre la techumbre y no sobre el muro, y por eso se calcula sobre la
     * superficie de faldon, que es mayor que la planta: un techo de 50% de
     * pendiente cubre casi 12% mas metros de los que ocupa en el suelo.
     */
    case Cubierta = 'cubierta';

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
            // La membrana se despliega corrida sobre la fachada y se recorta
            // despues. La cubierta no descuenta nada porque el techo no tiene
            // vanos: una claraboya se resta aparte cuando exista.
            self::Membrana, self::Cubierta => false,
            default => true,
        };
    }

    /** Si la capa va sobre la techumbre y no sobre los muros. */
    public function esDeTechumbre(): bool
    {
        return $this === self::Cubierta;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Arriostramiento => 'Arriostramiento / tablero estructural',
            self::RevestimientoInterior => 'Revestimiento interior',
            self::RevestimientoExterior => 'Revestimiento exterior',
            self::Aislante => 'Aislante termico',
            self::Membrana => 'Membrana hidrofuga',
            self::Cubierta => 'Cubierta',
        };
    }
}
