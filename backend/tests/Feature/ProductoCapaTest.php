<?php

use App\Models\ProductoCapa;
use App\Services\Capas\ConsumoCapaService;
use App\Services\Capas\TipoCapa;
use Database\Seeders\ProductoCapaSeeder;

beforeEach(fn () => (new ProductoCapaSeeder)->run());

function producto(string $like): ProductoCapa
{
    return ProductoCapa::where('nombre', 'like', $like)->firstOrFail();
}

describe('rendimiento derivado de la geometría', function () {
    it('calcula una plancha por su superficie', function () {
        expect(producto('OSB estructural 11,1%')->rendimientoM2())->toBe(2.9768)
            ->and(producto('Yeso-cartón 8 mm%')->rendimientoM2())->toBe(2.88);
    });

    it('descuenta el traslape del siding, que se instala montado', function () {
        // Una tabla de 190 mm con 30 de solape sólo deja 160 a la vista:
        // 0,160 x 3,66 = 0,5856 m2, no los 0,6954 de su superficie total.
        $siding = producto('Siding fibrocemento 190 x 3660 x 6%');

        expect($siding->anchoUtilMm())->toBe(160)
            ->and($siding->rendimientoM2())->toBe(0.5856);
    });

    it('multiplica por las piezas cuando se vende por caja', function () {
        expect(producto('%caja de 10')->rendimientoM2())->toBe(5.856);
    });

    it('usa el rendimiento de etiqueta en los rollos, que no tienen pieza que medir', function () {
        expect(producto('Membrana hidrófuga%')->rendimientoM2())->toBe(75.0)
            ->and(producto('Lana de vidrio 50 mm%')->rendimientoM2())->toBe(12.0);
    });
});

describe('el interior no es sólo yeso-cartón', function () {
    it('ofrece terciado ranurado como alternativa', function () {
        $interiores = ProductoCapa::where('tipo', TipoCapa::RevestimientoInterior)->pluck('nombre');

        expect($interiores->filter(fn ($n) => str_contains($n, 'Terciado ranurado')))->not->toBeEmpty()
            ->and($interiores->filter(fn ($n) => str_contains($n, 'Yeso-cartón')))->not->toBeEmpty();
    });

    it('cada tipo de capa ofrece más de un producto', function () {
        foreach (TipoCapa::cases() as $tipo) {
            expect(ProductoCapa::where('tipo', $tipo)->count())
                ->toBeGreaterThan(1, "El tipo {$tipo->value} debería ofrecer alternativas");
        }
    });
});

describe('lo que cambia en el presupuesto', function () {
    it('el siding se cotiza por tabla y no por plancha', function () {
        // Cotizarlo con el rendimiento de una plancha de OSB daba 16 unidades
        // donde hacen falta 83: faltaban cuatro de cada cinco tablas.
        $consumo = (new ConsumoCapaService)->para(
            producto('Siding fibrocemento 190 x 3660 x 6%')->aCapa(mermaPct: 10),
            48.0,
            44.0,
        );

        expect($consumo->cantidadComprar)->toBe(83.0)
            ->and($consumo->capa->unidadVenta)->toBe('tabla');
    });

    it('comprar por caja da el mismo material en menos unidades', function () {
        $servicio = new ConsumoCapaService;

        $tablas = $servicio->para(producto('Siding fibrocemento 190 x 3660 x 6%')->aCapa(mermaPct: 10), 48.0, 44.0);
        $cajas = $servicio->para(producto('%caja de 10')->aCapa(mermaPct: 10), 48.0, 44.0);

        // 83 tablas sueltas contra 9 cajas de 10: la caja obliga a redondear de a
        // diez, así que sobra más.
        expect($cajas->cantidadComprar * 10)->toBeGreaterThanOrEqual($tablas->cantidadComprar);
    });
});
