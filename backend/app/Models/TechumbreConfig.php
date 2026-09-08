<?php

namespace App\Models;

use App\Services\Madera\ParametrosCorte;
use App\Services\Techumbre\ConfiguracionTechumbre;
use App\Support\Medida;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La techumbre guardada de un proyecto.
 *
 * @property int $id
 * @property int $proyecto_id
 * @property int $aguas
 * @property int $luz_mm
 * @property int $largo_mm
 * @property int $altura_cumbrera_mm
 * @property int $alero_mm
 * @property string $unidad_ingreso
 * @property int $escuadria_id
 * @property int $escuadria_ancho_mm
 * @property int $escuadria_alto_mm
 * @property int|null $escuadria_costanera_id
 * @property int $largo_comercial_mm
 * @property int $separacion_cerchas_mm
 * @property int $separacion_costaneras_mm
 * @property string $separacion_unidad_ingreso
 * @property numeric-string $merma_pct
 * @property-read Escuadria|null $escuadriaCostanera
 */
#[Table(name: 'techumbre_configs')]
#[Fillable([
    'proyecto_id', 'aguas', 'luz_mm', 'largo_mm', 'altura_cumbrera_mm', 'alero_mm',
    'unidad_ingreso', 'escuadria_id', 'escuadria_ancho_mm', 'escuadria_alto_mm',
    'escuadria_costanera_id', 'largo_comercial_mm', 'separacion_cerchas_mm',
    'separacion_costaneras_mm', 'separacion_unidad_ingreso', 'merma_pct',
])]
class TechumbreConfig extends Model
{
    protected function casts(): array
    {
        return [
            'aguas' => 'integer',
            'luz_mm' => 'integer',
            'largo_mm' => 'integer',
            'altura_cumbrera_mm' => 'integer',
            'alero_mm' => 'integer',
            'escuadria_ancho_mm' => 'integer',
            'escuadria_alto_mm' => 'integer',
            'largo_comercial_mm' => 'integer',
            'separacion_cerchas_mm' => 'integer',
            'separacion_costaneras_mm' => 'integer',
            'merma_pct' => 'decimal:2',
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
    public function escuadriaCostanera(): BelongsTo
    {
        return $this->belongsTo(Escuadria::class, 'escuadria_costanera_id');
    }

    public function aDominio(): ConfiguracionTechumbre
    {
        $costanera = $this->escuadriaCostanera;

        return new ConfiguracionTechumbre(
            aguas: $this->aguas,
            luz: Medida::desdeMm($this->luz_mm, $this->unidad_ingreso),
            largo: Medida::desdeMm($this->largo_mm, $this->unidad_ingreso),
            alturaCumbrera: Medida::desdeMm($this->altura_cumbrera_mm, $this->unidad_ingreso),
            escuadriaAncho: Medida::desdeMm($this->escuadria_ancho_mm),
            escuadriaAlto: Medida::desdeMm($this->escuadria_alto_mm),
            separacionCerchas: Medida::desdeMm($this->separacion_cerchas_mm, $this->separacion_unidad_ingreso),
            separacionCostaneras: Medida::desdeMm($this->separacion_costaneras_mm, $this->separacion_unidad_ingreso),
            costaneraAncho: $costanera?->ancho() ?? Medida::desdeMm($this->escuadria_ancho_mm),
            costaneraAlto: $costanera?->alto() ?? Medida::desdeMm($this->escuadria_alto_mm),
            alero: Medida::desdeMm($this->alero_mm, $this->unidad_ingreso),
            corte: new ParametrosCorte(
                Medida::desdeMm($this->largo_comercial_mm),
                (float) $this->merma_pct,
            ),
        );
    }
}
