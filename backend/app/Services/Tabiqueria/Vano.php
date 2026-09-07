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
        /**
         * Desde que pie derecho arranca el marco del vano, contando desde 1.
         *
         * Se elige en tramos y no en metros porque el vano tiene que arrancar
         * sobre un pie derecho igual: ubicarlo con una regla permitiria dejarlo a
         * 37 cm del anterior, que es justo el error que la trama evita. Nulo
         * significa "ubicalo tu": el calculo reparte los vanos parejo, que es lo
         * correcto mientras el usuario todavia no decide donde van.
         */
        public ?int $desdeTramo = null,
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

        if ($desdeTramo !== null && $desdeTramo < 1) {
            throw new InvalidArgumentException('El tramo de arranque se cuenta desde 1.');
        }

        // Varios vanos iguales no pueden compartir posicion. Si se quiere ubicar
        // cada uno, van como entradas separadas con su propio tramo.
        if ($desdeTramo !== null && $cantidad > 1) {
            throw new InvalidArgumentException(
                'Un vano con posicion fija va de a uno: para varios, agrega una entrada por cada uno.'
            );
        }
    }

    public static function puerta(Medida $ancho, Medida $alto, int $cantidad = 1, ?int $desdeTramo = null): self
    {
        return new self(TipoVano::Puerta, $ancho, $alto, Medida::cero(), $cantidad, $desdeTramo);
    }

    public static function ventana(Medida $ancho, Medida $alto, Medida $antepecho, int $cantidad = 1, ?int $desdeTramo = null): self
    {
        return new self(TipoVano::Ventana, $ancho, $alto, $antepecho, $cantidad, $desdeTramo);
    }

    /**
     * Lo que ocupa el vano a lo largo del muro, marco incluido.
     *
     * De centro a centro de las dos jambas hay el ancho del hueco mas tres
     * espesores de pieza: la jamba, el apoyo de un lado y el apoyo del otro.
     */
    public function anchoConMarco(Medida $espesorPieza): Medida
    {
        return $this->ancho->mas($espesorPieza->por(3));
    }

    /** Donde arranca el marco, medido desde el extremo del muro. */
    public function inicioEn(Medida $separacion): ?Medida
    {
        return $this->desdeTramo === null ? null : $separacion->por($this->desdeTramo - 1);
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
