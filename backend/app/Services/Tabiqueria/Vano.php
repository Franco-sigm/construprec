<?php

namespace App\Services\Tabiqueria;

use App\Support\Medida;
use InvalidArgumentException;

/**
 * Una puerta o ventana en una cara, con su marco estructural.
 */
final readonly class Vano
{
    public function __construct(
        public TipoVano $tipo,
        public Medida $ancho,
        public Medida $alto,
        public Medida $antepecho,
        public int $cantidad = 1,
    ) {
        if ($cantidad < 1) {
            throw new InvalidArgumentException('Un vano debe tener cantidad de al menos 1.');
        }

        if ($ancho->esCero() || $alto->esCero()) {
            throw new InvalidArgumentException('Un vano no puede tener ancho o alto cero.');
        }

        // Una puerta arranca del piso por definicion. Si llegara con antepecho,
        // el despiece le pondria piezas debajo que en obra no existen.
        if ($tipo === TipoVano::Puerta && ! $antepecho->esCero()) {
            throw new InvalidArgumentException('Una puerta no puede tener antepecho.');
        }
    }

    public static function puerta(Medida $ancho, Medida $alto, int $cantidad = 1): self
    {
        return new self(TipoVano::Puerta, $ancho, $alto, Medida::cero(), $cantidad);
    }

    public static function ventana(Medida $ancho, Medida $alto, Medida $antepecho, int $cantidad = 1): self
    {
        return new self(TipoVano::Ventana, $ancho, $alto, $antepecho, $cantidad);
    }

    /** Altura total que el vano ocupa desde el piso: lo que deben salvar las jambas. */
    public function altoTotal(): Medida
    {
        return $this->antepecho->mas($this->alto);
    }

    /** Superficie del hueco, en m2. Es lo que se descuenta de los recubrimientos. */
    public function superficieM2(): float
    {
        return round($this->ancho->porM2($this->alto) * $this->cantidad, 4);
    }
}
