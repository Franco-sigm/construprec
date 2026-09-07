<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuantas unidades de una moneda equivalen a 1 USD en una fecha.
 *
 * Todas las tasas van contra el dolar. Guardar los pares directos obligaria a
 * mantener n^2 combinaciones; con USD de pivote son n filas y cualquier par se
 * arma con dos saltos.
 */
#[Table(name: 'tasas_cambio')]
#[Fillable(['moneda', 'tasa', 'fecha', 'fuente'])]
class TasaCambio extends Model
{
    protected function casts(): array
    {
        return [
            'tasa' => 'decimal:8',
            'fecha' => 'date',
        ];
    }

    /** @return BelongsTo<Moneda, $this> */
    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda', 'codigo');
    }
}
