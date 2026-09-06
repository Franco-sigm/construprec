<?php

namespace App\Services\Tabiqueria;

use App\Support\Medida;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Traduce la geometria de una cara a la lista de piezas de madera que hay que
 * cortar.
 *
 * Modela armado de plataforma, que es lo estandar en tabiqueria de madera: pies
 * derechos a separacion constante entre soleras, y cada vano enmarcado por
 * jambas, pies derechos de apoyo y un dintel que traspasa a las jambas la carga
 * que el vano interrumpe.
 *
 * LO QUE NO MODELA, a proposito: encuentros entre tabiques perpendiculares,
 * arriostramientos diagonales, cadenetas intermedias y refuerzos de esquina. Son
 * decisiones que dependen del proyecto estructural, no de la geometria del muro,
 * y meterlas aca daria una precision que el dato de entrada no respalda.
 */
final class DespieceService
{
    /**
     * @param  list<Vano>  $vanos
     */
    public function deCara(Medida $largo, Medida $alto, array $vanos, ConfiguracionTabique $config): Despiece
    {
        $this->validar($largo, $alto, $vanos, $config);

        $altoPieDerecho = $config->altoPieDerecho($alto);
        $piezas = [];

        // --- soleras: corren de punta a punta de la cara ---
        // No se descuenta el ancho de las puertas aunque interrumpan la solera
        // inferior: en obra la solera se instala corrida y se recorta despues, asi
        // que el metraje que se compra es el del muro completo.
        if ($config->soleraInferior) {
            $piezas[] = new Pieza(RolPieza::SoleraInferior, $largo, 1);
        }

        $piezas[] = new Pieza(RolPieza::SoleraSuperior, $largo, $config->solerasSuperiores);

        // --- pies derechos de campo ---
        // Uno cada `separacion` mas el de cierre: un muro de 6 m a 40 cm lleva 15
        // vanos entre ejes y por lo tanto 16 pies derechos.
        $pilaresBase = (int) ceil($largo->dividirPor($config->separacion)) + 1;

        foreach ($vanos as $vano) {
            $piezas = [...$piezas, ...$this->enmarcarVano($vano, $altoPieDerecho, $config)];

            // El vano se come los pies derechos de campo que caian dentro de el.
            // Se usa floor y no round para no descontar de mas: es preferible que
            // sobre una pieza a que el muro quede sin apoyo.
            $removidos = (int) floor($vano->ancho->dividirPor($config->separacion));
            $pilaresBase -= $removidos * $vano->cantidad;
        }

        if ($pilaresBase > 0) {
            $piezas[] = new Pieza(RolPieza::PieDerecho, $altoPieDerecho, $pilaresBase);
        }

        $vanosM2 = array_sum(array_map(fn (Vano $v) => $v->superficieM2(), $vanos));

        return Despiece::deCara(
            array_values(array_filter($piezas, fn (Pieza $p) => $p->cantidad > 0)),
            $largo,
            $alto,
            $vanosM2,
        );
    }

    /**
     * Piezas que enmarcan un vano.
     *
     * @return list<Pieza>
     */
    private function enmarcarVano(Vano $vano, Medida $altoPieDerecho, ConfiguracionTabique $config): array
    {
        $n = $vano->cantidad;
        $altoTotal = $vano->altoTotal();

        $piezas = [
            // Dos jambas de altura completa a cada lado: son las que reciben del
            // dintel la carga que el vano interrumpe y la bajan hasta la solera.
            new Pieza(RolPieza::Jamba, $altoPieDerecho, 2 * $n),

            // Dos pies derechos de apoyo, cortados a la altura del vano, sobre los
            // que descansa el dintel.
            new Pieza(RolPieza::PieDerechoApoyo, $altoTotal, 2 * $n),

            // El dintel no mide lo mismo que el vano: se apoya sobre los dos pies
            // derechos de apoyo, asi que cruza el ancho del hueco mas el espesor de
            // ambos.
            new Pieza(RolPieza::Dintel, $vano->ancho->mas($config->escuadriaAncho->por(2)), $n),
        ];

        // Cuantas piezas cortas caben en el ancho del vano, con la misma separacion
        // que el resto del muro para que el revestimiento siempre encuentre apoyo.
        $cortosPorVano = (int) ceil($vano->ancho->dividirPor($config->separacion)) + 1;

        if ($vano->tipo === TipoVano::Ventana) {
            $piezas[] = new Pieza(RolPieza::Alfeizar, $vano->ancho, $n);

            // Tramo entre la solera inferior y el alfeizar. Es lo que evita contar
            // pies derechos enteros donde en realidad va una ventana.
            $altoBajoVano = $vano->antepecho->menos($config->escuadriaAncho);

            if (! $altoBajoVano->esCero()) {
                $piezas[] = new Pieza(RolPieza::PieDerechoBajoVano, $altoBajoVano, $cortosPorVano * $n);
            }
        }

        // Tramo entre el dintel y la solera superior. Puede no existir: en un vano
        // alto el dintel llega pegado a la solera y no queda hueco que rellenar.
        $altoSobreVano = $altoPieDerecho->menos($altoTotal)->menos($config->altoDintel());

        if (! $altoSobreVano->esCero()) {
            $piezas[] = new Pieza(RolPieza::PieDerechoSobreVano, $altoSobreVano, $cortosPorVano * $n);
        }

        return $piezas;
    }

    /**
     * @param  list<Vano>  $vanos
     */
    private function validar(Medida $largo, Medida $alto, array $vanos, ConfiguracionTabique $config): void
    {
        if ($largo->esCero() || $alto->esCero()) {
            throw new UnprocessableEntityHttpException('La cara debe tener largo y alto mayores que cero.');
        }

        if ($config->altoPieDerecho($alto)->esCero()) {
            throw new UnprocessableEntityHttpException(
                'Las soleras ocupan todo el alto de la cara: no queda altura para los pies derechos.'
            );
        }

        $anchoVanos = array_reduce(
            $vanos,
            fn (int $suma, Vano $v) => $suma + ($v->ancho->mm * $v->cantidad),
            0,
        );

        if ($anchoVanos > $largo->mm) {
            throw new UnprocessableEntityHttpException(
                'Los vanos suman mas ancho que la cara que los contiene.'
            );
        }

        foreach ($vanos as $vano) {
            if ($vano->altoTotal()->mayorQue($config->altoPieDerecho($alto))) {
                throw new UnprocessableEntityHttpException(
                    'Un vano llega mas alto que el espacio disponible entre soleras.'
                );
            }
        }
    }
}
