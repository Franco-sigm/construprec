<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un precio de un material en una fecha.
 *
 * Es historial y no una columna en `materiales` porque en un rubro con inflacion
 * "cuanto costaba esto en marzo" es una pregunta habitual, y sin historial la
 * respuesta se pierde en cada actualizacion.
 */
#[Table(name: 'precios_material')]
#[Fillable(['material_id', 'precio', 'moneda', 'vigente_desde', 'proveedor'])]
class PrecioMaterial extends Model
{
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:4',
            'vigente_desde' => 'date',
        ];
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** @return BelongsTo<Moneda, $this> */
    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda', 'codigo');
    }
}
