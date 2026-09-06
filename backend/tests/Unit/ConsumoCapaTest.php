<?php

use App\Services\Capas\AplicacionCapa;
use App\Services\Capas\Capa;
use App\Services\Capas\ConsumoCapaService;
use App\Services\Capas\TipoCapa;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Plancha de OSB de 1,22 x 2,44: rinde 2,9768 m2 y se compra entera. */
function osb(array $sobrescribir = []): Capa
{
    return new Capa(...array_merge([
        'tipo' => TipoCapa::Arriostramiento,
        'nombre' => 'OSB 11,1 mm',
        'unidadVenta' => 'plancha',
        'rendimientoM2' => 2.9768,
    ], $sobrescribir));
}

describe('cuantas unidades comprar', function () {
    it('divide la superficie por lo que rinde una unidad', function () {
        // 44,00 / 2,9768 = 14,780973... -> 14,781 planchas.
        $c = (new ConsumoCapaService)->para(osb(), 48.0, 44.0);

        expect($c->cantidad)->toBe(14.781);
    });

    it('sube al siguiente entero lo que se compra entero', function () {
        $c = (new ConsumoCapaService)->para(osb(), 48.0, 44.0);

        expect($c->cantidadComprar)->toBe(15.0);
    });

    it('no redondea lo que se vende fraccionado', function () {
        // Redondear 14,78 a 15 en un material que se corta a medida es cotizar
        // de mas sin razon.
        $c = (new ConsumoCapaService)->para(osb(['fraccionable' => true]), 48.0, 44.0);

        expect($c->cantidadComprar)->toBe(14.781);
    });

    it('informa cuanto sobra de la ultima unidad', function () {
        // Se compran 15 pero se ocupan 14,781: sobra 0,219 de plancha, o sea
        // 0,6519 m2 de tablero que quedan de recorte.
        $c = (new ConsumoCapaService)->para(osb(), 48.0, 44.0);

        expect($c->sobranteM2())->toBe(0.6519);
    });

    it('rechaza un rendimiento que no permite calcular nada', function () {
        osb(['rendimientoM2' => 0.0]);
    })->throws(InvalidArgumentException::class, 'debe ser mayor que cero');
});

describe('vanos: no todas las capas los descuentan', function () {
    it('el aislante los descuenta, porque va dentro de la cavidad', function () {
        $aislante = new Capa(
            tipo: TipoCapa::Aislante,
            nombre: 'Lana de vidrio 50 mm',
            unidadVenta: 'rollo',
            rendimientoM2: 12.0,
        );

        $c = (new ConsumoCapaService)->para($aislante, 48.0, 44.0);

        expect($c->superficieBaseM2)->toBe(44.0);
    });

    it('la membrana no los descuenta, porque se corre entera y se recorta despues', function () {
        $membrana = new Capa(
            tipo: TipoCapa::Membrana,
            nombre: 'Membrana hidrofuga',
            unidadVenta: 'rollo',
            rendimientoM2: 75.0,
        );

        $c = (new ConsumoCapaService)->para($membrana, 48.0, 44.0);

        expect($c->superficieBaseM2)->toBe(48.0)
            ->and($c->cantidadComprar)->toBe(1.0);
    });

    it('cada capa puede contradecir el criterio de su tipo', function () {
        $membrana = new Capa(
            tipo: TipoCapa::Membrana,
            nombre: 'Membrana hidrofuga',
            unidadVenta: 'rollo',
            rendimientoM2: 75.0,
            descuentaVanos: true,
        );

        expect((new ConsumoCapaService)->para($membrana, 48.0, 44.0)->superficieBaseM2)->toBe(44.0);
    });
});

describe('caras que reviste', function () {
    it('duplica la superficie en un tabique revestido por ambos lados', function () {
        // Sin esto un divisorio queda cotizado a la mitad: un muro, dos caras.
        $c = (new ConsumoCapaService)->para(
            osb(['tipo' => TipoCapa::RevestimientoInterior, 'aplicacion' => AplicacionCapa::Ambas]),
            48.0,
            44.0,
        );

        expect($c->superficieAplicadaM2)->toBe(88.0)
            ->and($c->cantidadComprar)->toBe(30.0);
    });

    it('no la duplica cuando va por un solo lado', function () {
        $c = (new ConsumoCapaService)->para(osb(['aplicacion' => AplicacionCapa::Interior]), 48.0, 44.0);

        expect($c->superficieAplicadaM2)->toBe(44.0);
    });
});

describe('merma', function () {
    it('se aplica sobre la superficie y no sobre el conteo de unidades', function () {
        // 44 + 10% = 48,4 m2 -> 16,26 planchas -> 17.
        // Aplicada al reves seria ceil(14,78) x 1,10 = 16,5 -> 17 tambien aca,
        // pero sobre una capa que viene justa agrega una unidad entera de mas.
        $c = (new ConsumoCapaService)->para(osb(['mermaPct' => 10.0]), 48.0, 44.0);

        expect($c->superficieConMermaM2)->toBe(48.4)
            ->and($c->cantidadComprar)->toBe(17.0);
    });

    it('no cambia nada cuando es cero', function () {
        $c = (new ConsumoCapaService)->para(osb(), 48.0, 44.0);

        expect($c->superficieConMermaM2)->toBe($c->superficieAplicadaM2);
    });
});

describe('datos que no cierran', function () {
    it('rechaza calcular capas sin superficie', function () {
        (new ConsumoCapaService)->para(osb(), 0.0, 0.0);
    })->throws(UnprocessableEntityHttpException::class, 'No hay superficie');
});
