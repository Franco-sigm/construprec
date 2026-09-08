<?php

namespace App\Models;

use App\Services\Capas\AplicacionCapa;
use App\Services\Capas\Capa;
use App\Services\Capas\TipoCapa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Un producto concreto con que materializar una capa.
 *
 * @property int $id
 * @property TipoCapa $tipo
 * @property string $nombre
 * @property string|null $marca
 * @property string $unidad_venta
 * @property int|null $largo_mm
 * @property int|null $ancho_mm
 * @property int $traslape_mm
 * @property int|null $separacion_costaneras_mm
 * @property bool $requiere_tablero
 * @property int $piezas_por_unidad
 * @property numeric-string|null $rendimiento_m2
 * @property bool $fraccionable
 * @property numeric-string $merma_sugerida_pct
 * @property bool $activo
 */
#[Table(name: 'productos_capa')]
#[Fillable([
    'tipo', 'nombre', 'marca', 'unidad_venta', 'largo_mm', 'ancho_mm', 'espesor_mm',
    'traslape_mm', 'separacion_costaneras_mm', 'requiere_tablero',
    'piezas_por_unidad', 'rendimiento_m2', 'fraccionable',
    'merma_sugerida_pct', 'pais', 'activo',
])]
class ProductoCapa extends Model
{
    protected function casts(): array
    {
        return [
            'tipo' => TipoCapa::class,
            'largo_mm' => 'integer',
            'ancho_mm' => 'integer',
            'espesor_mm' => 'decimal:2',
            'traslape_mm' => 'integer',
            'separacion_costaneras_mm' => 'integer',
            'requiere_tablero' => 'boolean',
            'piezas_por_unidad' => 'integer',
            'rendimiento_m2' => 'decimal:4',
            'fraccionable' => 'boolean',
            'merma_sugerida_pct' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * Cuanta superficie cubre una unidad de venta.
     *
     * Se deriva de la geometria siempre que la haya, en vez de guardarse a mano:
     * asi el traslape entra en la cuenta sin que nadie tenga que acordarse. Una
     * tabla de siding de 190 mm de ancho instalada con 30 de traslape deja 160 a
     * la vista, y cotizarla por sus 190 deja la obra corta en un 16%.
     *
     * Los rollos declaran su rendimiento directo: vienen con los metros cuadrados
     * en la etiqueta y no hay pieza que medir.
     */
    public function rendimientoM2(): float
    {
        if ($this->rendimiento_m2 !== null) {
            return (float) $this->rendimiento_m2;
        }

        $anchoUtil = max(0, ($this->ancho_mm ?? 0) - $this->traslape_mm);
        $porPieza = $anchoUtil * ($this->largo_mm ?? 0) / 1_000_000;

        return round($porPieza * $this->piezas_por_unidad, 4);
    }

    /** Lo que se ve de cada pieza una vez instalada. Cero traslape = el ancho completo. */
    public function anchoUtilMm(): ?int
    {
        return $this->ancho_mm === null ? null : max(0, $this->ancho_mm - $this->traslape_mm);
    }

    /** Convierte el producto del catalogo en la capa que consume el calculo. */
    public function aCapa(AplicacionCapa $aplicacion = AplicacionCapa::Exterior, ?float $mermaPct = null): Capa
    {
        return new Capa(
            tipo: $this->tipo,
            nombre: $this->nombre,
            unidadVenta: $this->unidad_venta,
            rendimientoM2: $this->rendimientoM2(),
            aplicacion: $aplicacion,
            fraccionable: $this->fraccionable,
            mermaPct: $mermaPct ?? (float) $this->merma_sugerida_pct,
        );
    }
}
