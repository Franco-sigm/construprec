<?php

namespace App\Services\Techumbre;

use App\Services\Madera\Despiece;
use App\Services\Madera\Pieza;
use App\Services\Madera\RolPieza;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Traduce la geometria del techo a la lista de piezas que hay que cortar.
 *
 * Modela cercha de pendolon, que es la que lleva alma: dos pares, el tirante que
 * los amarra de muro a muro, el pendolon al centro y dos diagonales que van del
 * pie del pendolon a la mitad de cada par. Seis piezas por cercha en dos aguas.
 *
 * El tirante no es opcional ni decorativo: sin el, cada par empuja su muro hacia
 * afuera y la cercha se abre sola. Por eso una cercha sin tirante —par y
 * nudillo— es otro sistema y no una variante de este; agregarlo seria otro metodo
 * aca, no un parametro.
 *
 * LO QUE NO MODELA, a proposito: cerchas de refuerzo en los extremos, arriostres
 * entre cerchas, cadenetas de costanera y la fijacion de la cubierta. Dependen del
 * proyecto estructural y del fabricante de la cubierta, no de la geometria.
 */
final class DespieceTechumbreService
{
    public function despiezar(ConfiguracionTechumbre $config): Despiece
    {
        $this->validar($config);

        $cerchas = $this->cuantasCerchas($config);
        $piezas = [
            ...$this->piezasDeCercha($config, $cerchas),
            ...$this->costaneras($config),
        ];

        // El techo no tiene vanos: una claraboya se descuenta aparte cuando exista.
        return Despiece::desdePiezas(
            array_values(array_filter($piezas, fn (Pieza $p) => $p->cantidad > 0)),
            // La superficie del faldón incluye el vuelo por los cuatro costados:
            // el alero también hay que cubrirlo.
            $config->largo->mas($config->alero->por(2)),
            $config->largoPar()->por($config->aguas),
            0.0,
            $config->escuadriaAncho,
        );
    }

    /** Una cercha cada `separacion` mas la de cierre, igual que los pies derechos. */
    public function cuantasCerchas(ConfiguracionTechumbre $config): int
    {
        return (int) ceil($config->largo->dividirPor($config->separacionCerchas)) + 1;
    }

    /**
     * Filas de costanera sobre un faldon.
     *
     * Se cuentan sobre el par ya inclinado y no sobre la proyeccion horizontal:
     * medir en planta deja el faldon corto justo donde mas se nota, en el alero.
     */
    public function filasDeCostanera(ConfiguracionTechumbre $config): int
    {
        return (int) ceil($config->largoPar()->dividirPor($config->separacionCostaneras)) + 1;
    }

    /**
     * @return list<Pieza>
     */
    private function piezasDeCercha(ConfiguracionTechumbre $config, int $cerchas): array
    {
        // En dos aguas cada cercha lleva dos pares y dos diagonales, uno por
        // faldon. En una agua hay un solo faldon, asi que va la mitad.
        $porFaldon = $config->aguas;

        return [
            new Pieza(RolPieza::Par, $config->largoPar(), $cerchas * $porFaldon),

            // El tirante cruza la luz completa y se apoya en los dos muros. Es la
            // pieza mas larga de la cercha y la que suele definir la escuadria.
            new Pieza(RolPieza::Tirante, $config->luz, $cerchas),

            new Pieza(RolPieza::Pendolon, $config->alturaCumbrera, $cerchas),
            new Pieza(RolPieza::Diagonal, $config->largoDiagonal(), $cerchas * $porFaldon),
        ];
    }

    /**
     * @return list<Pieza>
     */
    private function costaneras(ConfiguracionTechumbre $config): array
    {
        $piezas = [
            new Pieza(
                RolPieza::Costanera,
                $config->largo,
                $this->filasDeCostanera($config) * $config->aguas,
            ),
        ];

        // La cumbrera solo existe si hay dos faldones que encontrar. En una agua
        // el borde alto lo remata la ultima costanera.
        if ($config->aguas === 2) {
            $piezas[] = new Pieza(RolPieza::Cumbrera, $config->largo, 1);
        }

        return $piezas;
    }

    private function validar(ConfiguracionTechumbre $config): void
    {
        // Con la cercha completa, el tirante tiene que salir de una sola tira: un
        // empalme a media luz es justo donde el tirante mas trabaja a traccion.
        if ($config->luz->mayorQue($config->corte->largoComercial)) {
            throw new UnprocessableEntityHttpException(sprintf(
                'El tirante mide %s y la tira comercial es de %s: no se puede empalmar a media luz, que es donde más trabaja. Hay que elegir una tira más larga o dividir la techumbre.',
                $config->luz,
                $config->corte->largoComercial,
            ));
        }

        if ($config->largoPar()->mayorQue($config->corte->largoComercial)) {
            throw new UnprocessableEntityHttpException(sprintf(
                'El par mide %s con el alero y la tira comercial es de %s.',
                $config->largoPar(),
                $config->corte->largoComercial,
            ));
        }

        // Una pendiente muy baja no evacúa el agua y una muy alta deja de ser un
        // techo habitable. El límite real lo pone el fabricante de la cubierta.
        if ($config->pendientePorcentaje() < 5) {
            throw new UnprocessableEntityHttpException(sprintf(
                'La pendiente queda en %s%%: bajo 5%% el agua no corre y se empoza.',
                $config->pendientePorcentaje(),
            ));
        }
    }
}
