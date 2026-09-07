<?php

use App\Services\Capas\ConsumoCapaService;
use App\Services\Presupuesto\Calculadora;
use App\Services\Presupuesto\Cara;
use App\Services\Tabiqueria\ConfiguracionTabique;
use App\Services\Tabiqueria\DespieceService;
use App\Services\Tabiqueria\PlanCorteService;
use App\Services\Tabiqueria\RolPieza;
use App\Support\Medida;
use App\Support\Unidad;

function motor(): Calculadora
{
    return new Calculadora(new DespieceService, new PlanCorteService, new ConsumoCapaService);
}

function configEsquina(int $piezasPorEsquina = 3): ConfiguracionTabique
{
    return new ConfiguracionTabique(
        escuadriaAncho: Medida::desdeMm(41),
        escuadriaAlto: Medida::desdeMm(65),
        separacion: Medida::de(0.4, Unidad::Metro),
        largoComercial: Medida::de(3.2, Unidad::Metro),
        piezasPorEsquina: $piezasPorEsquina,
    );
}

/** Planta rectangular de 6 x 4: cuatro caras, cuatro encuentros. */
function planta(): array
{
    $m = fn (float $v) => Medida::de($v, Unidad::Metro);

    return [
        new Cara($m(6), $m(2.4)),
        new Cara($m(4), $m(2.4)),
        new Cara($m(6), $m(2.4)),
        new Cara($m(4), $m(2.4)),
    ];
}

describe('refuerzo de esquina', function () {
    it('descuenta los dos pies derechos que ya aportan las caras', function () {
        // En el encuentro ya hay dos: el de cierre de un muro y el de arranque
        // del otro. Un armado de tres agrega uno, no tres.
        expect(configEsquina(3)->piezasExtraPorEsquina())->toBe(1)
            ->and(configEsquina(4)->piezasExtraPorEsquina())->toBe(2)
            ->and(configEsquina(2)->piezasExtraPorEsquina())->toBe(0);
    });

    it('agrega una pieza por encuentro con el armado de tres', function () {
        $calculo = motor()->calcular(planta(), configEsquina(3), esquinas: 4);

        expect($calculo->despiece->cantidadDe(RolPieza::PosteEsquina))->toBe(4);
    });

    it('las corta al alto del pie derecho, no del muro', function () {
        // 2,40 menos las dos soleras de 41 mm.
        $calculo = motor()->calcular(planta(), configEsquina(3), esquinas: 4);

        expect($calculo->despiece->porRol(RolPieza::PosteEsquina)[0]->largo->mm)->toBe(2318);
    });

    it('no agrega nada si las caras no forman contorno cerrado', function () {
        // Una pared suelta no tiene encuentros.
        $calculo = motor()->calcular(planta(), configEsquina(3), esquinas: 0);

        expect($calculo->despiece->cantidadDe(RolPieza::PosteEsquina))->toBe(0);
    });

    it('se puede desactivar dejando la esquina en dos piezas', function () {
        $calculo = motor()->calcular(planta(), configEsquina(2), esquinas: 4);

        expect($calculo->despiece->cantidadDe(RolPieza::PosteEsquina))->toBe(0);
    });

    it('no se cuenta dos veces, una por cada cara del encuentro', function () {
        // Es el error que acecha: la esquina es de los dos muros, así que
        // contarla dentro del despiece de cada cara la cotizaría doble.
        $calculo = motor()->calcular(planta(), configEsquina(3), esquinas: 4);

        expect($calculo->despiece->cantidadDe(RolPieza::PosteEsquina))->toBe(4)
            ->and($calculo->despiece->cantidadDe(RolPieza::PosteEsquina))->not->toBe(8);
    });

    it('cuesta cuatro tiras en una planta de 6 x 4', function () {
        $sin = motor()->calcular(planta(), configEsquina(2), esquinas: 4);
        $con = motor()->calcular(planta(), configEsquina(3), esquinas: 4);

        // Un poste mide 2,318: de una tira de 3,2 no salen dos, así que cada uno
        // se lleva una tira. Es el precio de tener dónde clavar la plancha.
        expect($con->planCorte->tirasNetas() - $sin->planCorte->tirasNetas())->toBe(4);
    });

    it('toma el alto de la cara más alta cuando difieren', function () {
        $m = fn (float $v) => Medida::de($v, Unidad::Metro);
        $mixtas = [new Cara($m(6), $m(2.4)), new Cara($m(4), $m(3.0))];

        $calculo = motor()->calcular($mixtas, configEsquina(3), esquinas: 2);

        // 3,00 menos las soleras. Recortar es trivial; alargar no se puede.
        expect($calculo->despiece->porRol(RolPieza::PosteEsquina)[0]->largo->mm)->toBe(2918);
    });
});
