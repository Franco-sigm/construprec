<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una linea del presupuesto, con la fotografia del material al momento de
 * cotizar.
 *
 * Todo lo que se copia del catalogo se guarda aca. Sin esa copia, subir el
 * precio de un material cambiaria todos los presupuestos ya emitidos, incluido
 * el que el cliente tiene en mano.
 */
/**
 * @property int $id
 * @property int $presupuesto_id
 * @property int|null $material_id
 * @property int $orden
 * @property string $origen
 * @property string $etapa
 * @property numeric-string $cantidad_comprar
 * @property numeric-string $precio_unitario
 * @property numeric-string $tasa_cambio
 * @property numeric-string $subtotal
 * @property array<string, mixed>|null $detalle
 */
#[Fillable([
    'presupuesto_id', 'material_id', 'orden', 'origen', 'etapa',
    'magnitud', 'unidad_magnitud', 'merma_pct',
    'nombre_material', 'categoria_material', 'unidad_venta', 'rendimiento',
    'precio_unitario', 'moneda_origen', 'tasa_cambio',
    'cantidad', 'cantidad_comprar', 'subtotal', 'detalle',
])]
class PresupuestoLinea extends Model
{
    public const ORIGEN_ESTRUCTURA = 'estructura';

    public const ORIGEN_CAPA = 'capa';

    public const ORIGEN_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'magnitud' => 'decimal:4',
            'merma_pct' => 'decimal:2',
            'rendimiento' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'tasa_cambio' => 'decimal:8',
            'cantidad' => 'decimal:4',
            'cantidad_comprar' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'detalle' => 'array',
        ];
    }

    /** @return BelongsTo<Presupuesto, $this> */
    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Subtotal de una linea, ya en la moneda del presupuesto.
     *
     * Se calcula sobre la cantidad a comprar y no sobre la que se ocupa: se paga
     * lo que se compra, y media plancha de OSB se cobra entera.
     *
     * Es estatico para poder resolverlo antes de construir la fila. Guardar el
     * subtotal ya calculado, en vez de asignarlo despues, evita que exista un
     * instante en que la linea esta en la base con total cero.
     */
    public static function subtotalDe(float $cantidadComprar, float $precioUnitario, float $tasaCambio): float
    {
        return round($cantidadComprar * $precioUnitario * $tasaCambio, 4);
    }

    public function calcularSubtotal(): float
    {
        return self::subtotalDe(
            (float) $this->cantidad_comprar,
            (float) $this->precio_unitario,
            (float) $this->tasa_cambio,
        );
    }
}
