<?php

namespace App\Services\Madera;

use App\Support\Medida;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Convierte la lista de piezas en tiras comerciales que comprar.
 *
 * Empaqueta con "el primero que quepa, de mayor a menor" (first-fit
 * decreasing): ordena todos los cortes de mas largo a mas corto y mete cada uno
 * en la primera tira donde entre, abriendo una nueva solo si no cabe en
 * ninguna. Es una heuristica clasica, no el optimo, pero garantiza no usar mas
 * de un tercio extra sobre el minimo teorico y produce un plan que en obra se
 * puede seguir.
 *
 * Aprovechar el recorte es la razon de ser de todo esto: un pie derecho de
 * 2,318 m deja 0,882 m libres en una tira de 3,2 m, y un tramo bajo ventana de
 * 0,859 m cabe justo ahi. Calculando cada largo por separado esa madera se
 * compra dos veces.
 *
 * El orden de mayor a menor deja las cadenetas al final, que es donde tienen que
 * ir: al ser la pieza mas corta del tabique, entran en los recortes que ya dejaron
 * los cortes largos. En una planta de 6 x 4 eso significa que una fila completa de
 * cadenetas, casi 15 metros lineales, no cuesta ninguna tira adicional; solo baja
 * el desperdicio. Meterlas antes las repartiria en tiras propias y obligaria a
 * comprar madera para algo que ya estaba pagado.
 */
final class PlanCorteService
{
    /**
     * @param  list<Pieza>  $piezas
     */
    public function para(array $piezas, ParametrosCorte $parametros): PlanCorte
    {
        $largoComercial = $parametros->largoComercial;

        [$corridas, $sueltas] = $this->separarPorTipo($piezas);

        // Soleras: admiten empalme, asi que basta cubrir los metros lineales.
        // El empalme tiene que caer sobre un pie derecho, cosa que esta cuenta no
        // verifica; esa diferencia la absorbe la merma.
        $metrosCorridas = round(array_sum(array_map(fn (Pieza $p) => $p->metrosLineales(), $corridas)), 4);
        $tirasCorridas = (int) ceil($metrosCorridas / $largoComercial->metros());

        return new PlanCorte(
            tiras: $this->empaquetar($sueltas, $largoComercial, $parametros->anchoCorteMm),
            tirasCorridas: $tirasCorridas,
            metrosCorridas: $metrosCorridas,
            largoComercial: $largoComercial,
            mermaPct: $parametros->mermaPct,
        );
    }

    /**
     * @param  list<Pieza>  $piezas
     * @return array{list<Pieza>, list<Pieza>}
     */
    private function separarPorTipo(array $piezas): array
    {
        $corridas = [];
        $sueltas = [];

        foreach ($piezas as $pieza) {
            if ($pieza->rol->esCorrida()) {
                $corridas[] = $pieza;
            } else {
                $sueltas[] = $pieza;
            }
        }

        return [$corridas, $sueltas];
    }

    /**
     * @param  list<Pieza>  $piezas
     * @return list<Tira>
     */
    private function empaquetar(array $piezas, Medida $largoComercial, int $anchoCorteMm): array
    {
        $cortes = $this->expandirYOrdenar($piezas, $largoComercial);

        /** @var list<Tira> $tiras */
        $tiras = [];

        foreach ($cortes as $corte) {
            $colocado = false;

            foreach ($tiras as $i => $tira) {
                if ($tira->cabe($corte->largo)) {
                    $tiras[$i] = $tira->con($corte);
                    $colocado = true;
                    break;
                }
            }

            if (! $colocado) {
                $tiras[] = new Tira([$corte], $largoComercial, $anchoCorteMm);
            }
        }

        return $tiras;
    }

    /**
     * Un corte por unidad, del mas largo al mas corto.
     *
     * Se expanden los grupos porque el empaquetado decide pieza por pieza: dos
     * cortes del mismo largo pueden terminar en tiras distintas segun con que se
     * combinen.
     *
     * @param  list<Pieza>  $piezas
     * @return list<Pieza>
     */
    private function expandirYOrdenar(array $piezas, Medida $largoComercial): array
    {
        $cortes = [];

        foreach ($piezas as $pieza) {
            if ($pieza->largo->mayorQue($largoComercial)) {
                throw new UnprocessableEntityHttpException(sprintf(
                    'Una pieza de %s (%s) no sale de una tira de %s: hay que elegir un largo comercial mayor.',
                    $pieza->largo,
                    $pieza->rol->etiqueta(),
                    $largoComercial,
                ));
            }

            for ($i = 0; $i < $pieza->cantidad; $i++) {
                $cortes[] = $pieza->conCantidad(1);
            }
        }

        // De mayor a menor: colocar primero lo largo deja huecos grandes que las
        // piezas cortas rellenan. Al reves, las cortas ocupan tiras enteras y
        // despues no hay donde poner las largas.
        usort($cortes, fn (Pieza $a, Pieza $b) => $b->largo->mm <=> $a->largo->mm);

        return $cortes;
    }
}
