<?php

namespace App\Models;

use App\Support\Medida;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Escuadria comercial de madera: el "2x3" con su medida real.
 *
 * La nominal es como se pide en la barraca; la real es con la que hay que
 * calcular. Confundirlas descuadra el despiece.
 */
/**
 * @property int $id
 * @property string $nominal
 * @property int $ancho_real_mm
 * @property int $alto_real_mm
 * @property string $estado
 * @property list<int> $largos_comerciales_mm
 * @property string $pais
 * @property bool $activa
 */
#[Fillable(['nominal', 'ancho_real_mm', 'alto_real_mm', 'estado', 'largos_comerciales_mm', 'pais', 'activa'])]
class Escuadria extends Model
{
    protected function casts(): array
    {
        return [
            'ancho_real_mm' => 'integer',
            'alto_real_mm' => 'integer',
            'largos_comerciales_mm' => 'array',
            'activa' => 'boolean',
        ];
    }

    /** Espesor de la pieza: lo que ocupa un pie derecho a lo largo del muro. */
    public function ancho(): Medida
    {
        return Medida::desdeMm($this->ancho_real_mm);
    }

    /** Profundidad del tabique: lo que mide de alto un dintel puesto de canto. */
    public function alto(): Medida
    {
        return Medida::desdeMm($this->alto_real_mm);
    }

    /** Como se nombra en la barraca: "2x3 seco cepillado". */
    public function descripcion(): string
    {
        return $this->nominal.' '.str_replace('_', ' ', $this->estado);
    }

    /**
     * El largo comercial mas corto que sirve para cortar esta pieza.
     *
     * Elegir el mas corto que alcance deja menos recorte: cortar piezas de 2,4 m
     * desde tiras de 4 m bota 1,6 m en cada una.
     */
    public function largoParaCortar(Medida $pieza): ?Medida
    {
        $candidatos = array_filter($this->largos_comerciales_mm, fn (int $l) => $l >= $pieza->mm);

        return $candidatos === [] ? null : Medida::desdeMm(min($candidatos));
    }
}
