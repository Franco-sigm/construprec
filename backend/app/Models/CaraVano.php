<?php

namespace App\Models;

use App\Services\Tabiqueria\TipoVano;
use App\Services\Tabiqueria\Vano;
use App\Support\Medida;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una puerta o ventana en una cara.
 */
/**
 * @property int $id
 * @property int $cara_id
 * @property TipoVano $tipo
 * @property int $ancho_mm
 * @property int $alto_mm
 * @property int $antepecho_mm
 * @property int $cantidad
 * @property int|null $desde_tramo
 * @property string $unidad_ingreso
 */
#[Fillable([
    'cara_id', 'tipo', 'ancho_mm', 'alto_mm', 'antepecho_mm', 'cantidad',
    'desde_tramo', 'unidad_ingreso',
])]
class CaraVano extends Model
{
    protected function casts(): array
    {
        return [
            'tipo' => TipoVano::class,
            'ancho_mm' => 'integer',
            'alto_mm' => 'integer',
            'antepecho_mm' => 'integer',
            'cantidad' => 'integer',
            'desde_tramo' => 'integer',
        ];
    }

    /** @return BelongsTo<ProyectoCara, $this> */
    public function cara(): BelongsTo
    {
        return $this->belongsTo(ProyectoCara::class, 'cara_id');
    }

    public function aDominio(): Vano
    {
        return new Vano(
            tipo: $this->tipo,
            ancho: Medida::desdeMm($this->ancho_mm, $this->unidad_ingreso),
            alto: Medida::desdeMm($this->alto_mm, $this->unidad_ingreso),
            antepecho: Medida::desdeMm($this->antepecho_mm, $this->unidad_ingreso),
            cantidad: $this->cantidad,
            desdeTramo: $this->desde_tramo,
        );
    }
}
