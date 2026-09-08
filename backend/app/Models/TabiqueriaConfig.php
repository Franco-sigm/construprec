<?php

namespace App\Models;

use App\Services\Tabiqueria\ConfiguracionTabique;
use App\Support\Medida;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Como se arma la tabiqueria del proyecto.
 *
 * Las medidas de la escuadria se copian aca al configurar. Si manana se corrige
 * el catalogo, un proyecto ya calculado no puede cambiar de dimensiones por
 * debajo.
 */
/**
 * @property int $id
 * @property int $proyecto_id
 * @property int $escuadria_id
 * @property int $escuadria_ancho_mm
 * @property int $escuadria_alto_mm
 * @property int $largo_comercial_mm
 * @property int $separacion_mm
 * @property string $separacion_unidad_ingreso
 * @property bool $solera_inferior
 * @property int $soleras_superiores
 * @property int|null $escuadria_dintel_id
 * @property numeric-string $merma_pct
 * @property int $filas_cadenetas
 * @property int $piezas_por_esquina
 * @property-read Escuadria|null $escuadriaDintel
 */
#[Fillable([
    'proyecto_id', 'escuadria_id', 'escuadria_ancho_mm', 'escuadria_alto_mm',
    'largo_comercial_mm', 'separacion_mm', 'separacion_unidad_ingreso',
    'solera_inferior', 'soleras_superiores', 'escuadria_dintel_id',
    'merma_pct', 'filas_cadenetas', 'piezas_por_esquina',
])]
class TabiqueriaConfig extends Model
{
    protected function casts(): array
    {
        return [
            'escuadria_ancho_mm' => 'integer',
            'escuadria_alto_mm' => 'integer',
            'largo_comercial_mm' => 'integer',
            'separacion_mm' => 'integer',
            'solera_inferior' => 'boolean',
            'soleras_superiores' => 'integer',
            'merma_pct' => 'decimal:2',
            'filas_cadenetas' => 'integer',
            'piezas_por_esquina' => 'integer',
        ];
    }

    /** @return BelongsTo<Proyecto, $this> */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /** @return BelongsTo<Escuadria, $this> */
    public function escuadria(): BelongsTo
    {
        return $this->belongsTo(Escuadria::class);
    }

    /** @return BelongsTo<Escuadria, $this> */
    public function escuadriaDintel(): BelongsTo
    {
        return $this->belongsTo(Escuadria::class, 'escuadria_dintel_id');
    }

    public function aDominio(): ConfiguracionTabique
    {
        return new ConfiguracionTabique(
            escuadriaAncho: Medida::desdeMm($this->escuadria_ancho_mm),
            escuadriaAlto: Medida::desdeMm($this->escuadria_alto_mm),
            separacion: Medida::desdeMm($this->separacion_mm, $this->separacion_unidad_ingreso),
            largoComercial: Medida::desdeMm($this->largo_comercial_mm),
            soleraInferior: $this->solera_inferior,
            solerasSuperiores: $this->soleras_superiores,
            escuadriaDintelAlto: $this->escuadriaDintel?->alto(),
            mermaPct: (float) $this->merma_pct,
            filasCadenetas: $this->filas_cadenetas,
            piezasPorEsquina: $this->piezas_por_esquina,
        );
    }
}
