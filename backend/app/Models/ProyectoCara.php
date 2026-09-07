<?php

namespace App\Models;

use App\Services\Tabiqueria\Vano;
use App\Support\Medida;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un muro del proyecto, con sus puertas y ventanas.
 */
/**
 * @property int $id
 * @property int $proyecto_id
 * @property int $orden
 * @property string $nombre
 * @property int $largo_mm
 * @property int $alto_mm
 * @property string $unidad_ingreso
 * @property bool $es_exterior
 * @property-read Collection<int, CaraVano> $vanos
 */
#[Fillable(['proyecto_id', 'orden', 'nombre', 'largo_mm', 'alto_mm', 'unidad_ingreso', 'es_exterior'])]
class ProyectoCara extends Model
{
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'largo_mm' => 'integer',
            'alto_mm' => 'integer',
            'es_exterior' => 'boolean',
        ];
    }

    /** @return BelongsTo<Proyecto, $this> */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /** @return HasMany<CaraVano, $this> */
    public function vanos(): HasMany
    {
        return $this->hasMany(CaraVano::class, 'cara_id');
    }

    public function largo(): Medida
    {
        return Medida::desdeMm($this->largo_mm, $this->unidad_ingreso);
    }

    public function alto(): Medida
    {
        return Medida::desdeMm($this->alto_mm, $this->unidad_ingreso);
    }

    /**
     * Los vanos como objetos de calculo.
     *
     * @return list<Vano>
     */
    public function vanosDominio(): array
    {
        return $this->vanos->map(fn (CaraVano $v) => $v->aDominio())->all();
    }
}
