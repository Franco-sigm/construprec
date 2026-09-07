<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un producto del catalogo del usuario, con su historial de precios.
 *
 * En el flujo del asistente el usuario escribe el precio a mano al final, pero
 * el catalogo sirve para proponerle el ultimo que pago y para heredar el
 * rendimiento sin que lo tenga que recordar.
 */
/**
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property string|null $categoria
 * @property string $unidad_venta
 * @property numeric-string|null $rendimiento
 * @property bool $fraccionable
 * @property bool $activo
 */
#[Table(name: 'materiales')]
#[Fillable([
    'user_id', 'nombre', 'categoria', 'descripcion', 'unidad_venta', 'tipo_calculo',
    'largo_mm', 'ancho_mm', 'espesor_mm', 'rendimiento', 'unidad_rendimiento',
    'merma_pct', 'fraccionable', 'activo',
])]
class Material extends Model
{
    protected function casts(): array
    {
        return [
            'largo_mm' => 'integer',
            'ancho_mm' => 'integer',
            'espesor_mm' => 'decimal:2',
            'rendimiento' => 'decimal:4',
            'merma_pct' => 'decimal:2',
            'fraccionable' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PrecioMaterial, $this> */
    public function precios(): HasMany
    {
        return $this->hasMany(PrecioMaterial::class);
    }

    /**
     * El precio mas reciente que se cargo.
     *
     * Se ordena tambien por id y no solo por fecha: dos precios cargados el mismo
     * dia empatan, y sin desempate cual gana queda a criterio del motor de base de
     * datos, o sea puede cambiar entre consultas.
     */
    public function precioVigente(): ?PrecioMaterial
    {
        return $this->precios()
            ->orderByDesc('vigente_desde')
            ->orderByDesc('id')
            ->first();
    }
}
