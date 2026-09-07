<?php

namespace App\Models;

use App\Services\Capas\AplicacionCapa;
use App\Services\Capas\Capa;
use App\Services\Capas\TipoCapa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una capa de recubrimiento del proyecto con el producto elegido.
 */
/**
 * @property int $id
 * @property int $proyecto_id
 * @property TipoCapa $tipo
 * @property AplicacionCapa $aplicacion
 * @property int|null $material_id
 * @property string $nombre
 * @property string $unidad_venta
 * @property numeric-string $rendimiento
 * @property string $unidad_rendimiento
 * @property bool $fraccionable
 * @property numeric-string $merma_pct
 * @property bool|null $descuenta_vanos
 * @property int $orden
 */
#[Fillable([
    'proyecto_id', 'tipo', 'aplicacion', 'material_id', 'nombre', 'unidad_venta',
    'rendimiento', 'unidad_rendimiento', 'fraccionable', 'merma_pct',
    'descuenta_vanos', 'orden',
])]
class ProyectoCapa extends Model
{
    protected function casts(): array
    {
        return [
            'tipo' => TipoCapa::class,
            'aplicacion' => AplicacionCapa::class,
            'rendimiento' => 'decimal:4',
            'fraccionable' => 'boolean',
            'merma_pct' => 'decimal:2',
            'descuenta_vanos' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /** @return BelongsTo<Proyecto, $this> */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function aDominio(): Capa
    {
        return new Capa(
            tipo: $this->tipo,
            nombre: $this->nombre,
            unidadVenta: $this->unidad_venta,
            rendimientoM2: (float) $this->rendimiento,
            aplicacion: $this->aplicacion,
            fraccionable: $this->fraccionable,
            mermaPct: (float) $this->merma_pct,
            descuentaVanos: $this->descuenta_vanos,
        );
    }
}
