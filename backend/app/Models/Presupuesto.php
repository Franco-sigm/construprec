<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * El resultado del calculo, congelado.
 *
 * Un presupuesto emitido no se recalcula nunca: es un documento historico, no
 * una consulta viva. Por eso el total se guarda en vez de sumarse al vuelo, y
 * cada linea lleva copiado el precio con que se cotizo.
 */
/**
 * @property int $id
 * @property int $user_id
 * @property int|null $proyecto_id
 * @property string $nombre
 * @property string $moneda_destino
 * @property string $estado
 * @property Carbon|null $fecha
 * @property numeric-string $total
 * @property-read Moneda|null $moneda
 */
#[Fillable(['user_id', 'proyecto_id', 'nombre', 'descripcion', 'moneda_destino', 'fecha', 'estado', 'total'])]
class Presupuesto extends Model
{
    public const BORRADOR = 'borrador';

    public const EMITIDO = 'emitido';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Proyecto, $this> */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /** @return BelongsTo<Moneda, $this> */
    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_destino', 'codigo');
    }

    /** @return HasMany<PresupuestoLinea, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(PresupuestoLinea::class)->orderBy('orden');
    }

    public function estaEmitido(): bool
    {
        return $this->estado === self::EMITIDO;
    }

    /**
     * Suma las lineas y guarda el total.
     *
     * Redondea a los decimales de la moneda destino: en pesos chilenos un total
     * con centavos es un monto que no se puede pagar.
     */
    public function recalcularTotal(): float
    {
        $suma = (float) $this->lineas()->sum('subtotal');
        $total = $this->moneda?->redondear($suma) ?? round($suma, 4);

        $this->forceFill(['total' => $total])->save();

        return $total;
    }
}
