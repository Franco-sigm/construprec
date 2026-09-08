<?php

namespace App\Models;

use App\Support\Medida;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Un proyecto de tabiqueria: la entrada geometrica, editable.
 *
 * Las medidas de la planta se guardan para poder regenerar las caras si el
 * usuario corrige el rectangulo, pero el calculo NO las lee: lee las caras, que
 * ya pudieron editarse una por una.
 */
/**
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property string $sistema
 * @property string $alcance
 * @property int|null $ancho_mm
 * @property int|null $largo_mm
 * @property int|null $alto_mm
 * @property string $unidad_ingreso
 * @property string $estado
 * @property-read Collection<int, ProyectoCara> $caras
 * @property-read Collection<int, ProyectoCapa> $capas
 * @property-read TabiqueriaConfig|null $tabiqueria
 * @property-read TechumbreConfig|null $techumbre
 */
#[Fillable([
    'user_id', 'nombre', 'descripcion', 'sistema', 'alcance',
    'ancho_mm', 'largo_mm', 'alto_mm', 'unidad_ingreso', 'estado',
])]
class Proyecto extends Model
{
    protected function casts(): array
    {
        return [
            'ancho_mm' => 'integer',
            'largo_mm' => 'integer',
            'alto_mm' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ProyectoCara, $this> */
    public function caras(): HasMany
    {
        return $this->hasMany(ProyectoCara::class)->orderBy('orden');
    }

    /** @return HasOne<TabiqueriaConfig, $this> */
    public function tabiqueria(): HasOne
    {
        return $this->hasOne(TabiqueriaConfig::class);
    }

    /** @return HasOne<TechumbreConfig, $this> */
    public function techumbre(): HasOne
    {
        return $this->hasOne(TechumbreConfig::class);
    }

    /** @return HasMany<ProyectoCapa, $this> */
    public function capas(): HasMany
    {
        return $this->hasMany(ProyectoCapa::class)->orderBy('orden');
    }

    /**
     * Capas de una etapa. La cubierta va sobre el faldón, que mide más que la
     * planta: mezclarla con las del muro la cotizaría sobre metros equivocados.
     *
     * @return HasMany<ProyectoCapa, $this>
     */
    public function capasDe(string $etapa): HasMany
    {
        return $this->capas()->where('etapa', $etapa);
    }

    /** @return HasMany<Presupuesto, $this> */
    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class);
    }

    /**
     * Las cuatro caras que salen de una planta rectangular.
     *
     * Se generan pero no se imponen: quedan como filas editables, que es lo que
     * despues va a permitir plantas en L sin tocar el esquema.
     *
     * @return list<array<string, mixed>>
     */
    public function carasDeLaPlanta(): array
    {
        $caras = [];
        $pares = [['Cara 1', $this->largo_mm], ['Cara 2', $this->ancho_mm], ['Cara 3', $this->largo_mm], ['Cara 4', $this->ancho_mm]];

        foreach ($pares as $orden => [$nombre, $largo]) {
            $caras[] = [
                'orden' => $orden + 1,
                'nombre' => $nombre,
                'largo_mm' => (int) $largo,
                'alto_mm' => (int) $this->alto_mm,
                'unidad_ingreso' => $this->unidad_ingreso,
                'es_exterior' => true,
            ];
        }

        return $caras;
    }

    public function alto(): Medida
    {
        return Medida::desdeMm((int) $this->alto_mm, $this->unidad_ingreso);
    }
}
