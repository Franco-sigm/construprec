<?php

namespace App\Services\Presupuesto;

use App\Models\PresupuestoLinea;

/**
 * Un material que el proyecto necesita, con su cantidad ya resuelta y sin
 * precio.
 *
 * Es lo que alimenta la pantalla donde el usuario pone los precios uno por uno:
 * la cantidad no depende del precio, asi que se puede calcular y mostrar antes
 * de preguntarlo.
 *
 * Una escuadria es UN material aunque salgan de ella pies derechos, soleras,
 * jambas y cadenetas: en la barraca se compra un solo producto y se paga un solo
 * precio. El desglose por rol vive en `detalle`, para poder auditar de donde
 * salieron las 76 tiras sin ensuciar la lista de compra.
 */
final readonly class MaterialRequerido
{
    /** @param  array<string, mixed>  $detalle */
    public function __construct(
        public string $clave,
        public string $nombre,
        public string $unidadVenta,
        public float $magnitud,
        public string $unidadMagnitud,
        public float $cantidad,
        public float $cantidadComprar,
        public string $origen,
        /** muros | techumbre. De qué parte de la obra viene la partida. */
        public string $etapa = 'muros',
        public float $mermaPct = 0.0,
        public ?int $materialId = null,
        public array $detalle = [],
    ) {}

    public function esEstructura(): bool
    {
        return $this->origen === PresupuestoLinea::ORIGEN_ESTRUCTURA;
    }

    /** @return array<string, mixed> */
    public function paraFormulario(): array
    {
        return [
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'unidad_venta' => $this->unidadVenta,
            'cantidad_comprar' => $this->cantidadComprar,
            'material_id' => $this->materialId,
            'origen' => $this->origen,
            'etapa' => $this->etapa,
        ];
    }
}
