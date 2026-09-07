<?php

use App\Services\Tabiqueria\ConfiguracionTabique;
use App\Support\Medida;
use App\Support\Unidad;

function tabique(float $separacionM = 0.4, int $espesorMm = 41): ConfiguracionTabique
{
    return new ConfiguracionTabique(
        escuadriaAncho: Medida::desdeMm($espesorMm),
        escuadriaAlto: Medida::desdeMm(65),
        separacion: Medida::de($separacionM, Unidad::Metro),
        largoComercial: Medida::de(3.2, Unidad::Metro),
    );
}

describe('anchos que calzan con la trama', function () {
    it('los deriva de la separación menos tres espesores de pieza', function () {
        // El vano ocupa jamba + apoyo + vano + apoyo + jamba, así que entre los
        // centros de las dos jambas hay el ancho más tres espesores.
        $anchos = array_map(fn (Medida $m) => $m->mm, tabique()->anchosModulares(5));

        expect($anchos)->toBe([677, 1077, 1477, 1877]);
    });

    it('descarta los tramos que no dan un vano de verdad', function () {
        // 1 x 400 - 123 = 277 mm: no es una puerta ni una ventana.
        expect(tabique()->anchosModulares(5))->not->toContain(Medida::desdeMm(277));
    });

    it('cambia con la separación', function () {
        $anchos = array_map(fn (Medida $m) => $m->mm, tabique(0.6)->anchosModulares(4));

        expect($anchos)->toBe([477, 1077, 1677, 2277]);
    });

    it('cambia con la escuadría', function () {
        // Un 3x4 tiene 65 mm de espesor: 400k - 195.
        $anchos = array_map(fn (Medida $m) => $m->mm, tabique(0.4, 65)->anchosModulares(4));

        expect($anchos)->toBe([605, 1005, 1405]);
    });
});

describe('cuánto se aleja un vano de la trama', function () {
    it('reconoce el que calza exacto', function () {
        expect(tabique()->calzaConLaTrama(Medida::desdeMm(1077)))->toBeTrue()
            ->and(tabique()->tramosQueOcupa(Medida::desdeMm(1077)))->toBe(3.0);
    });

    it('detecta el que no calza y propone el más cercano', function () {
        // Una ventana de 1,20 ocupa 3,31 tramos: sobra un tercio de tramo, que
        // queda como tira angosta sin apoyo para el canto del revestimiento.
        $config = tabique();
        $ventana = Medida::de(1.2, Unidad::Metro);

        expect($config->calzaConLaTrama($ventana))->toBeFalse()
            ->and($config->tramosQueOcupa($ventana))->toBe(3.3075)
            ->and($config->anchoModularMasCercano($ventana)->mm)->toBe(1077);
    });

    it('propone el más cercano hacia arriba cuando corresponde', function () {
        // 1,40 está más cerca de 1477 que de 1077.
        expect(tabique()->anchoModularMasCercano(Medida::de(1.4, Unidad::Metro))->mm)->toBe(1477);
    });

    it('una puerta estándar de 900 no calza a 40 cm', function () {
        // Es el caso real: la puerta viene con su medida de fábrica y no se
        // estira. Lo que se puede ajustar es la separación, no la puerta.
        $config = tabique();
        $puerta = Medida::desdeMm(900);

        expect($config->calzaConLaTrama($puerta))->toBeFalse()
            ->and($config->anchoModularMasCercano($puerta)->mm)->toBe(1077);
    });
});
