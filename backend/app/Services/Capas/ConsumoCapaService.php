<?php

namespace App\Services\Capas;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Traduce la superficie del tabique en unidades de material a comprar.
 *
 * El orden de los pasos importa y no es intercambiable:
 *
 *   1. Elegir la superficie base: bruta o neta, segun si la capa descuenta vanos.
 *   2. Multiplicar por las caras que reviste.
 *   3. Aplicar la merma.
 *   4. Dividir por el rendimiento de una unidad de venta.
 *   5. Redondear hacia arriba, salvo que el material se compre fraccionado.
 *
 * Aplicar la merma antes de dividir y no despues es lo correcto: la perdida
 * ocurre sobre el material, no sobre el conteo de paquetes. Hacerlo al reves
 * sobre una capa que ya venia justa agrega una unidad entera donde solo faltaban
 * unos centimetros.
 */
final class ConsumoCapaService
{
    public function para(Capa $capa, float $superficieBrutaM2, float $superficieNetaM2): ConsumoCapa
    {
        if ($superficieBrutaM2 <= 0) {
            throw new UnprocessableEntityHttpException(
                'No hay superficie sobre la que calcular las capas de recubrimiento.'
            );
        }

        $base = $capa->descuentaVanos() ? $superficieNetaM2 : $superficieBrutaM2;

        $aplicada = round($base * $capa->aplicacion->factor(), 4);
        $conMerma = round($aplicada * (1 + $capa->mermaPct / 100), 4);

        $cantidad = round($conMerma / $capa->rendimientoM2, 4);

        // Una plancha de OSB se compra entera y hay que subir al siguiente numero.
        // Un material que se corta a medida y se vende por metro no: redondear 3,2
        // a 4 seria cotizar de mas sin razon.
        $comprar = $capa->fraccionable ? $cantidad : (float) ceil($cantidad);

        return new ConsumoCapa(
            capa: $capa,
            superficieBaseM2: $base,
            superficieAplicadaM2: $aplicada,
            superficieConMermaM2: $conMerma,
            cantidad: $cantidad,
            cantidadComprar: $comprar,
        );
    }

    /**
     * @param  list<Capa>  $capas
     * @return list<ConsumoCapa>
     */
    public function paraTodas(array $capas, float $superficieBrutaM2, float $superficieNetaM2): array
    {
        return array_map(
            fn (Capa $capa) => $this->para($capa, $superficieBrutaM2, $superficieNetaM2),
            $capas,
        );
    }
}
