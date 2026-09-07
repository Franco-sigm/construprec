<?php

use App\Services\Madera\Despiece;
use App\Services\Madera\PlanCorteService;
use App\Services\Madera\RolPieza;
use App\Services\Tabiqueria\ConfiguracionTabique;
use App\Services\Tabiqueria\DespieceService;
use App\Services\Tabiqueria\Vano;
use App\Support\Medida;
use App\Support\Unidad;

function conCadenetas(int $filas): ConfiguracionTabique
{
    return new ConfiguracionTabique(
        escuadriaAncho: Medida::desdeMm(41),
        escuadriaAlto: Medida::desdeMm(65),
        separacion: Medida::de(0.4, Unidad::Metro),
        largoComercial: Medida::de(3.2, Unidad::Metro),
        filasCadenetas: $filas,
    );
}

function mt(float $v): Medida
{
    return Medida::de($v, Unidad::Metro);
}

describe('largo de corte', function () {
    it('descuenta un espesor de pieza de la separacion entre ejes', function () {
        // La separacion se mide entre ejes: 400 mm menos los 41 del pie derecho
        // dan la luz libre real, 359 mm.
        expect(conCadenetas(1)->largoCadeneta()->mm)->toBe(359);
    });

    it('se ajusta a la escuadria y a la separacion elegidas', function () {
        $config = new ConfiguracionTabique(
            escuadriaAncho: Medida::desdeMm(66),
            escuadriaAlto: Medida::desdeMm(91),
            separacion: Medida::de(0.6, Unidad::Metro),
            largoComercial: Medida::de(3.2, Unidad::Metro),
        );

        expect($config->largoCadeneta()->mm)->toBe(534);
    });
});

describe('cuantas van', function () {
    it('pone una por espacio libre entre pies derechos', function () {
        // 6,00 / 0,40 = 15 espacios.
        $d = (new DespieceService)->deCara(mt(6), mt(2.4), [], conCadenetas(1));

        expect($d->cantidadDe(RolPieza::Cadeneta))->toBe(15);
    });

    it('duplica con dos filas', function () {
        $d = (new DespieceService)->deCara(mt(6), mt(2.4), [], conCadenetas(2));

        expect($d->cantidadDe(RolPieza::Cadeneta))->toBe(30);
    });

    it('no pone ninguna donde hay un vano', function () {
        // El dintel y el alfeizar traban el vano: una cadeneta ahi sobra.
        // 15 espacios menos ceil(1,20 / 0,40) = 3 que ocupa la ventana.
        $d = (new DespieceService)->deCara(mt(6), mt(2.4), [Vano::ventana(mt(1.2), mt(1.0), mt(0.9))], conCadenetas(1));

        expect($d->cantidadDe(RolPieza::Cadeneta))->toBe(12);
    });

    it('se pueden desactivar', function () {
        $d = (new DespieceService)->deCara(mt(6), mt(2.4), [], conCadenetas(0));

        expect($d->cantidadDe(RolPieza::Cadeneta))->toBe(0);
    });
});

describe('salen del recorte, no de tiras nuevas', function () {
    // Es el punto de tenerlas: son la pieza mas corta del tabique, asi que el
    // empaquetado de mayor a menor las deja al final y entran en los huecos que
    // ya dejaron los cortes largos.
    $planta = function (int $filas) {
        $config = conCadenetas($filas);
        $s = new DespieceService;

        $t = Despiece::combinar(
            $s->deCara(mt(6), mt(2.4), [Vano::puerta(mt(0.9), mt(2.0)), Vano::ventana(mt(1.2), mt(1.0), mt(0.9))], $config),
            $s->deCara(mt(6), mt(2.4), [], $config),
            $s->deCara(mt(4), mt(2.4), [Vano::ventana(mt(1.0), mt(1.0), mt(0.9))], $config),
            $s->deCara(mt(4), mt(2.4), [], $config),
        );

        return [$t, (new PlanCorteService)->para($t->piezas, $config->parametrosCorte())];
    };

    it('una fila completa no cuesta ninguna tira adicional', function () use ($planta) {
        [, $sin] = $planta(0);
        [$con, $conUna] = $planta(1);

        expect($con->metrosLinealesDe(RolPieza::Cadeneta))->toBeGreaterThan(14.0)
            ->and($conUna->tirasNetas())->toBe($sin->tirasNetas());
    });

    it('dos filas tampoco, y bajan el desperdicio a menos de un cuarto', function () use ($planta) {
        [, $sin] = $planta(0);
        [, $conDos] = $planta(2);

        expect($conDos->tirasNetas())->toBe($sin->tirasNetas())
            ->and($conDos->desperdicioM())->toBeLessThan($sin->desperdicioM() / 4);
    });
});
