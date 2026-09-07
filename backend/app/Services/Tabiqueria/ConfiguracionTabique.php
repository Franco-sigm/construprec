<?php

namespace App\Services\Tabiqueria;

use App\Services\Madera\ParametrosCorte;
use App\Support\Medida;
use InvalidArgumentException;

/**
 * Como se arma la tabiqueria: que escuadria, cada cuanto y con cuantas soleras.
 *
 * Las dos medidas de la escuadria no son intercambiables y confundirlas descuadra
 * todo el despiece:
 *
 *   `escuadriaAncho` (los 41 mm de un 2x3) es el espesor de la pieza. Es lo que
 *   mide de alto una solera puesta de plano, y lo que ocupa un pie derecho a lo
 *   largo del muro.
 *
 *   `escuadriaAlto` (los 65 mm de un 2x3) es la profundidad del tabique. Es lo
 *   que mide de alto un dintel puesto de canto, y el espesor maximo de aislante
 *   que cabe en la cavidad.
 */
final readonly class ConfiguracionTabique
{
    public function __construct(
        public Medida $escuadriaAncho,
        public Medida $escuadriaAlto,
        public Medida $separacion,
        public Medida $largoComercial,
        public bool $soleraInferior = true,
        public int $solerasSuperiores = 1,
        public ?Medida $escuadriaDintelAlto = null,
        public float $mermaPct = 0.0,
        public int $filasCadenetas = 1,
        public int $anchoCorteMm = 3,
        /**
         * Piezas que lleva el encuentro de dos muros, contando las que ya aporta
         * cada cara.
         *
         * Dos es lo que sale solo: el pie derecho de cierre de una cara y el de
         * arranque de la otra. Tres es el armado habitual, que agrega una pieza
         * para que el canto de la plancha interior encuentre clavador. Cero
         * desactiva el refuerzo, para contrastar contra un calculo hecho a mano.
         */
        public int $piezasPorEsquina = 3,
    ) {
        if ($separacion->esCero()) {
            throw new InvalidArgumentException('La separacion entre pies derechos no puede ser cero.');
        }

        if ($largoComercial->esCero()) {
            throw new InvalidArgumentException('El largo comercial no puede ser cero.');
        }

        if ($solerasSuperiores < 1) {
            throw new InvalidArgumentException('Un tabique necesita al menos una solera superior.');
        }

        if ($mermaPct < 0) {
            throw new InvalidArgumentException('La merma no puede ser negativa.');
        }

        if ($filasCadenetas < 0) {
            throw new InvalidArgumentException('Las filas de cadenetas no pueden ser negativas.');
        }

        if ($anchoCorteMm < 0) {
            throw new InvalidArgumentException('El ancho de corte no puede ser negativo.');
        }

        if ($piezasPorEsquina < 0) {
            throw new InvalidArgumentException('Las piezas por esquina no pueden ser negativas.');
        }
    }

    /**
     * Largo de corte de una cadeneta: la luz libre entre dos pies derechos.
     *
     * La separacion se mide entre ejes, asi que hay que descontar un espesor
     * completo de pieza para llegar a lo que realmente hay que cortar. Con
     * separacion de 40 cm y un 2x3 de 41 mm, la cadeneta mide 359 mm y no 400.
     */
    public function largoCadeneta(): Medida
    {
        return $this->separacion->menos($this->escuadriaAncho);
    }

    /** Cuanto se come el alto del muro entre solera inferior y superiores. */
    public function espesorSoleras(): Medida
    {
        $cantidad = $this->solerasSuperiores + ($this->soleraInferior ? 1 : 0);

        return $this->escuadriaAncho->por($cantidad);
    }

    /**
     * Alto del pie derecho: el del muro menos lo que ocupan las soleras.
     * Es la medida de corte, no la del muro.
     */
    public function altoPieDerecho(Medida $altoCara): Medida
    {
        return $altoCara->menos($this->espesorSoleras());
    }

    /**
     * Anchos de vano que dejan las jambas justo sobre la trama de pies derechos.
     *
     * Un vano enmarcado ocupa, a lo largo del muro, esta secuencia de piezas:
     *
     *     jamba | apoyo | ---- vano ---- | apoyo | jamba
     *
     * De centro a centro de las dos jambas hay el ancho del vano mas tres
     * espesores de pieza. Para que ambas jambas caigan sobre la trama, esa
     * distancia tiene que ser multiplo de la separacion, de donde sale
     * `ancho = k x separacion - 3 x espesor`.
     *
     * Calzar importa por dos razones practicas. La primera es que si no calza
     * queda un tramo residual angosto al lado del vano, donde el revestimiento no
     * encuentra apoyo en su canto y hay que agregar una pieza a medida. La
     * segunda es que los pies derechos cortos sobre el dintel y bajo el antepecho
     * siguen la misma trama que el resto del muro, asi que una plancha de OSB o
     * de yeso-carton encuentra clavador cada 40 cm de punta a punta y no hay que
     * cortarla en un lugar caprichoso.
     *
     * Son sugerencias y no una imposicion: una puerta viene del fabricante con su
     * medida y no se puede estirar para que calce. Lo que si se puede es correr el
     * vano o ajustar la separacion.
     *
     * @return list<Medida>
     */
    public function anchosModulares(int $maximoTramos = 10): array
    {
        $descuento = $this->escuadriaAncho->por(3);
        $anchos = [];

        for ($k = 1; $k <= $maximoTramos; $k++) {
            $ancho = $this->separacion->por($k)->menos($descuento);

            // Con separacion chica los primeros tramos dan anchos que no son un
            // vano de verdad, o directamente cero.
            if ($ancho->mm >= 300) {
                $anchos[] = $ancho;
            }
        }

        return $anchos;
    }

    /**
     * Cuantos tramos de la trama consume un vano de este ancho.
     *
     * Entero exacto si calza; con decimales si no. La parte fraccionaria es
     * justamente lo que va a quedar como tramo residual.
     */
    public function tramosQueOcupa(Medida $anchoVano): float
    {
        return round(
            $anchoVano->mas($this->escuadriaAncho->por(3))->dividirPor($this->separacion),
            4,
        );
    }

    /** El ancho modular mas cercano al que se escribio. Nulo si no hay ninguno. */
    public function anchoModularMasCercano(Medida $anchoVano): ?Medida
    {
        $candidatos = $this->anchosModulares();

        if ($candidatos === []) {
            return null;
        }

        usort(
            $candidatos,
            fn (Medida $a, Medida $b) => abs($a->mm - $anchoVano->mm) <=> abs($b->mm - $anchoVano->mm),
        );

        return $candidatos[0];
    }

    /** Si el vano cae exacto sobre la trama, con una tolerancia de un milimetro. */
    public function calzaConLaTrama(Medida $anchoVano): bool
    {
        $cercano = $this->anchoModularMasCercano($anchoVano);

        return $cercano !== null && abs($cercano->mm - $anchoVano->mm) <= 1;
    }

    /** Lo que el plan de corte necesita: largo de tira, descarte y ancho de disco. */
    public function parametrosCorte(): ParametrosCorte
    {
        return new ParametrosCorte($this->largoComercial, $this->mermaPct, $this->anchoCorteMm);
    }

    /**
     * Piezas que hay que AGREGAR por cada esquina.
     *
     * Se descuentan las dos que ya vienen contadas, una por cada cara que llega
     * al encuentro. Sumarlas otra vez las cotizaria dos veces.
     */
    public function piezasExtraPorEsquina(): int
    {
        return max(0, $this->piezasPorEsquina - 2);
    }

    /** El dintel va de canto, asi que su alto es la profundidad del tabique salvo que se elija otra escuadria. */
    public function altoDintel(): Medida
    {
        return $this->escuadriaDintelAlto ?? $this->escuadriaAlto;
    }
}
