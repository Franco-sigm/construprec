<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Moneda ISO 4217 en que puede expresarse un presupuesto.
 *
 * La llave primaria es el codigo y no un autoincremental: es estable,
 * universal, y evita un join solo para mostrar "CLP" en cada linea.
 */
/**
 * @property string $codigo
 * @property string $nombre
 * @property string $simbolo
 * @property int $decimales
 * @property bool $activa
 */
#[Table(name: 'monedas', key: 'codigo', keyType: 'string', incrementing: false)]
#[Fillable(['codigo', 'nombre', 'simbolo', 'decimales', 'activa'])]
class Moneda extends Model
{
    protected function casts(): array
    {
        return [
            'decimales' => 'integer',
            'activa' => 'boolean',
        ];
    }

    /** @return HasMany<TasaCambio, $this> */
    public function tasas(): HasMany
    {
        return $this->hasMany(TasaCambio::class, 'moneda', 'codigo');
    }

    /**
     * Redondea un monto a los decimales que la moneda admite.
     *
     * El peso chileno y el guarani no aceptan fraccion: un total de $1.500,50
     * en pesos es un monto que nadie puede pagar.
     */
    public function redondear(float $monto): float
    {
        return round($monto, $this->decimales);
    }
}
