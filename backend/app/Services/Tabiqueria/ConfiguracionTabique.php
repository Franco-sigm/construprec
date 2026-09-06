<?php

namespace App\Services\Tabiqueria;

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

    /** El dintel va de canto, asi que su alto es la profundidad del tabique salvo que se elija otra escuadria. */
    public function altoDintel(): Medida
    {
        return $this->escuadriaDintelAlto ?? $this->escuadriaAlto;
    }
}
