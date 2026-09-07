<?php

namespace App\Services\Monedas;

use App\Models\TasaCambio;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Resuelve la tasa entre dos monedas cualesquiera pasando por el dolar.
 *
 * Las tasas se guardan solo contra USD. Guardar los pares directos obligaria a
 * mantener n^2 combinaciones y a actualizarlas todas cada dia; con el dolar de
 * pivote son n filas y cualquier par se arma con dos saltos.
 */
final class TasaCambioService
{
    /** El pivote. Por definicion vale 1 y no necesita fila en la tabla. */
    public const PIVOTE = 'USD';

    /** @var array<string, float> */
    private array $cache = [];

    /**
     * Cuantas unidades de `destino` equivale una unidad de `origen`.
     *
     * Si un dolar son 950 pesos y 3,7 soles, entonces un sol son 950/3,7 = 256,76
     * pesos: la tasa cruzada es el cociente de las dos tasas contra el pivote.
     */
    public function entre(string $origen, string $destino, ?Carbon $fecha = null): float
    {
        if ($origen === $destino) {
            return 1.0;
        }

        $fecha ??= Carbon::today();

        return round($this->contraPivote($destino, $fecha) / $this->contraPivote($origen, $fecha), 8);
    }

    /**
     * Cuantas unidades de la moneda equivalen a 1 USD.
     *
     * Toma la tasa mas reciente en la fecha pedida o antes, nunca una posterior:
     * un presupuesto fechado en marzo no puede cotizarse con el dolar de junio.
     */
    private function contraPivote(string $moneda, Carbon $fecha): float
    {
        if ($moneda === self::PIVOTE) {
            return 1.0;
        }

        $clave = $moneda.'|'.$fecha->toDateString();

        if (isset($this->cache[$clave])) {
            return $this->cache[$clave];
        }

        $tasa = TasaCambio::where('moneda', $moneda)
            ->whereDate('fecha', '<=', $fecha)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->value('tasa');

        if ($tasa === null) {
            throw new UnprocessableEntityHttpException(
                "No hay tasa de cambio de {$moneda} al {$fecha->toDateString()}: no se puede convertir el precio."
            );
        }

        if ((float) $tasa <= 0) {
            throw new UnprocessableEntityHttpException(
                "La tasa de cambio de {$moneda} es cero o negativa: convertir con ella daria un total sin sentido."
            );
        }

        return $this->cache[$clave] = (float) $tasa;
    }
}
