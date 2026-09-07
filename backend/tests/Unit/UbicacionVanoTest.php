<?php

use App\Services\Madera\RolPieza;
use App\Services\Tabiqueria\ConfiguracionTabique;
use App\Services\Tabiqueria\DespieceService;
use App\Services\Tabiqueria\TipoVano;
use App\Services\Tabiqueria\Vano;
use App\Support\Medida;
use App\Support\Unidad;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

function conf2x3(): ConfiguracionTabique
{
    return new ConfiguracionTabique(
        escuadriaAncho: Medida::desdeMm(41),
        escuadriaAlto: Medida::desdeMm(65),
        separacion: Medida::de(0.4, Unidad::Metro),
        largoComercial: Medida::de(3.2, Unidad::Metro),
    );
}

function met(float $v): Medida
{
    return Medida::de($v, Unidad::Metro);
}

describe('ubicar el vano en la trama', function () {
    it('traduce el número de pie derecho a una distancia del extremo', function () {
        // El pie derecho 1 está en el extremo, el 2 a 40 cm, el 5 a 1,60 m.
        $config = conf2x3();

        expect(Vano::ventana(met(1), met(1), met(0.9), 1, 1)->inicioEn($config->separacion)->mm)->toBe(0)
            ->and(Vano::ventana(met(1), met(1), met(0.9), 1, 5)->inicioEn($config->separacion)->mm)->toBe(1600);
    });

    it('deja sin posición al que se reparte solo', function () {
        expect(Vano::ventana(met(1), met(1), met(0.9))->inicioEn(conf2x3()->separacion))->toBeNull();
    });

    it('cuenta el ancho con marco, que es lo que ocupa en el muro', function () {
        // 1200 de hueco más tres espesores de 41.
        expect(Vano::ventana(met(1.2), met(1), met(0.9))->anchoConMarco(Medida::desdeMm(41))->mm)->toBe(1323);
    });

    it('rechaza ubicar varios vanos en el mismo lugar', function () {
        Vano::ventana(met(1), met(1), met(0.9), cantidad: 3, desdeTramo: 2);
    })->throws(InvalidArgumentException::class, 'va de a uno');

    it('cuenta los pies derechos desde 1 y no desde 0', function () {
        new Vano(TipoVano::Ventana, met(1), met(1), met(0.9), 1, 0);
    })->throws(InvalidArgumentException::class, 'se cuenta desde 1');
});

describe('el vano ubicado tiene que caber y no pisar a otro', function () {
    it('rechaza el que se sale de la cara', function () {
        // Una ventana de 1,20 con marco ocupa 1,323 m. Desde el pie derecho 14
        // (5,20 m) llegaría a 6,52 en una cara de 6.
        (new DespieceService)->deCara(met(6), met(2.4), [
            Vano::ventana(met(1.2), met(1), met(0.9), 1, 14),
        ], conf2x3());
    })->throws(UnprocessableEntityHttpException::class, 'no cabe');

    it('rechaza dos vanos superpuestos', function () {
        (new DespieceService)->deCara(met(6), met(2.4), [
            Vano::ventana(met(1.2), met(1), met(0.9), 1, 2),
            Vano::puerta(met(0.9), met(2), 1, 4),
        ], conf2x3());
    })->throws(UnprocessableEntityHttpException::class, 'se superponen');

    it('acepta dos vanos que no se tocan', function () {
        // El primero ocupa del pie derecho 2 al 5,3; el segundo arranca en el 7.
        $d = (new DespieceService)->deCara(met(8), met(2.4), [
            Vano::ventana(met(1.2), met(1), met(0.9), 1, 2),
            Vano::puerta(met(0.9), met(2), 1, 7),
        ], conf2x3());

        expect($d->cantidadDe(RolPieza::Jamba))->toBe(4);
    });

    it('no revisa solapes entre los que se reparten solos', function () {
        // Sin posición elegida los reparte el dibujo con espacios calculados, así
        // que ahí el solape no puede ocurrir.
        $d = (new DespieceService)->deCara(met(6), met(2.4), [
            Vano::ventana(met(1.2), met(1), met(0.9)),
            Vano::ventana(met(1.2), met(1), met(0.9)),
        ], conf2x3());

        expect($d->cantidadDe(RolPieza::Jamba))->toBe(4);
    });
});

describe('pies derechos que se come el vano', function () {
    it('cuenta los que quedan entre las dos jambas, no los que cruzan el hueco', function () {
        // Un vano de 1077 ocupa tres tramos exactos: se come dos pies derechos,
        // no tres. El tercero es la jamba del otro lado.
        $sin = (new DespieceService)->deCara(met(6), met(2.4), [], conf2x3());
        $con = (new DespieceService)->deCara(met(6), met(2.4), [
            Vano::ventana(Medida::desdeMm(1077), met(1), met(0.9), 1, 3),
        ], conf2x3());

        expect($sin->cantidadDe(RolPieza::PieDerecho))->toBe(16)
            ->and($con->cantidadDe(RolPieza::PieDerecho))->toBe(14);
    });

    it('da lo mismo dónde se ubique: el conteo no cambia', function () {
        // La posición cambia el dibujo, no el presupuesto: dos ventanas iguales
        // cuestan lo mismo estén donde estén.
        $servicio = new DespieceService;
        $izquierda = $servicio->deCara(met(6), met(2.4), [Vano::ventana(met(1.2), met(1), met(0.9), 1, 2)], conf2x3());
        $derecha = $servicio->deCara(met(6), met(2.4), [Vano::ventana(met(1.2), met(1), met(0.9), 1, 9)], conf2x3());

        expect($izquierda->metrosLinealesTotales())->toBe($derecha->metrosLinealesTotales());
    });
});
