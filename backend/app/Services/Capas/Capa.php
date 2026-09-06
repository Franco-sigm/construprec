<?php

namespace App\Services\Capas;

use InvalidArgumentException;

/**
 * Una capa de recubrimiento con el producto elegido para materializarla.
 *
 * El rendimiento es el dato central: cuanta superficie cubre UNA unidad de
 * venta. Una plancha de OSB de 1,22 x 2,44 rinde 2,9768 m2; un rollo de membrana
 * de 1,5 x 50 rinde 75 m2. De ahi sale cuantas unidades comprar.
 */
final readonly class Capa
{
    public function __construct(
        public TipoCapa $tipo,
        public string $nombre,
        public string $unidadVenta,
        public float $rendimientoM2,
        public AplicacionCapa $aplicacion = AplicacionCapa::Exterior,
        public bool $fraccionable = false,
        public float $mermaPct = 0.0,
        public ?bool $descuentaVanos = null,
    ) {
        if ($rendimientoM2 <= 0) {
            throw new InvalidArgumentException(
                "El rendimiento de {$nombre} debe ser mayor que cero: sin el no se puede saber cuantas unidades comprar."
            );
        }

        if ($mermaPct < 0) {
            throw new InvalidArgumentException('La merma no puede ser negativa.');
        }
    }

    /** Lo que decida la capa, y si no lo dijo, lo que corresponde a su tipo. */
    public function descuentaVanos(): bool
    {
        return $this->descuentaVanos ?? $this->tipo->descuentaVanosPorDefecto();
    }
}
