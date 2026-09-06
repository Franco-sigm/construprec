<?php

namespace App\Services\Capas;

/**
 * Cuanto material consume una capa y cuanto hay que comprar.
 *
 * Guarda los pasos intermedios y no solo el total porque el usuario tiene que
 * poder auditar el numero: entre "44 m2 de muro" y "17 planchas" hay tres
 * decisiones (descontar vanos o no, multiplicar por caras, aplicar merma) y
 * cualquiera de ellas puede ser la que no cuadra con lo que esperaba.
 */
final readonly class ConsumoCapa
{
    public function __construct(
        public Capa $capa,
        public float $superficieBaseM2,
        public float $superficieAplicadaM2,
        public float $superficieConMermaM2,
        public float $cantidad,
        public float $cantidadComprar,
    ) {}

    /** Lo que sobra de la ultima unidad. Cero si la capa es fraccionable. */
    public function sobranteM2(): float
    {
        return round(($this->cantidadComprar - $this->cantidad) * $this->capa->rendimientoM2, 4);
    }

    /** @return array<string, mixed> */
    public function detalle(): array
    {
        return [
            'tipo' => $this->capa->tipo->value,
            'etiqueta' => $this->capa->tipo->etiqueta(),
            'nombre' => $this->capa->nombre,
            'aplicacion' => $this->capa->aplicacion->value,
            'descuenta_vanos' => $this->capa->descuentaVanos(),
            'superficie_base_m2' => $this->superficieBaseM2,
            'superficie_aplicada_m2' => $this->superficieAplicadaM2,
            'superficie_con_merma_m2' => $this->superficieConMermaM2,
            'rendimiento_m2' => $this->capa->rendimientoM2,
            'unidad_venta' => $this->capa->unidadVenta,
            'cantidad' => $this->cantidad,
            'cantidad_comprar' => $this->cantidadComprar,
            'sobrante_m2' => $this->sobranteM2(),
        ];
    }
}
