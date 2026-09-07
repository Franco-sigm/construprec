<?php

use App\Models\Presupuesto;
use App\Models\PresupuestoLinea;
use App\Models\TasaCambio;
use App\Services\Capas\AplicacionCapa;
use App\Services\Capas\TipoCapa;
use App\Services\Monedas\TasaCambioService;
use App\Services\Presupuesto\ArmarPresupuestoService;
use App\Services\Presupuesto\CalculoProyectoService;
use Database\Seeders\MonedaSeeder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

function conCapas(): array
{
    return [
        ['tipo' => TipoCapa::Arriostramiento, 'aplicacion' => AplicacionCapa::Exterior, 'nombre' => 'OSB 11,1 mm',
            'unidad_venta' => 'plancha', 'rendimiento' => 2.9768, 'unidad_rendimiento' => 'm2', 'merma_pct' => 5],
        ['tipo' => TipoCapa::Membrana, 'aplicacion' => AplicacionCapa::Exterior, 'nombre' => 'Membrana hidrófuga',
            'unidad_venta' => 'rollo', 'rendimiento' => 75.0, 'unidad_rendimiento' => 'm2', 'merma_pct' => 10],
    ];
}

/**
 * Arma el presupuesto con precios dados por posicion: madera, OSB, membrana.
 *
 * Posicional y no por clave porque la clave de una capa lleva su id, y en MySQL
 * el contador de autoincremento no vuelve atras al revertir la transaccion de
 * cada test. Hardcodear "capa_1" pasa en SQLite y falla en MySQL.
 */
function armar(array $valores, string $moneda = 'CLP'): Presupuesto
{
    (new MonedaSeeder)->run();
    $proyecto = proyectoDePrueba(conCapas());
    $claves = app(CalculoProyectoService::class)->para($proyecto)->claves();

    return app(ArmarPresupuestoService::class)->para(
        $proyecto,
        array_combine(array_slice($claves, 0, count($valores)), $valores),
        $moneda,
    );
}

describe('el presupuesto completo', function () {
    it('multiplica cantidad por precio y suma el total', function () {
        // 76 tiras x 4.250 + 16 planchas x 18.990 + 1 rollo x 45.900
        $p = armar([4250, 18990, 45900]);

        expect($p->lineas)->toHaveCount(3)
            ->and((float) $p->total)->toBe(76.0 * 4250 + 16.0 * 18990 + 1.0 * 45900)
            // 323.000 + 303.840 + 45.900
            ->and((float) $p->total)->toBe(672740.0);
    });

    it('nace en borrador y colgando del proyecto', function () {
        $p = armar([4250, 18990, 45900]);

        expect($p->estado)->toBe(Presupuesto::BORRADOR)
            ->and($p->proyecto_id)->not->toBeNull();
    });

    it('cobra lo que se compra y no lo que se ocupa', function () {
        // Se ocupan 15,52 planchas de OSB pero se compran 16: media plancha se
        // paga entera.
        $p = armar([0, 1000, 0]);
        $osb = $p->lineas->firstWhere('nombre_material', 'OSB 11,1 mm');

        expect((float) $osb->cantidad)->toBe(15.52)
            ->and((float) $osb->cantidad_comprar)->toBe(16.0)
            ->and((float) $osb->subtotal)->toBe(16000.0);
    });

    it('congela la fotografía del material en cada línea', function () {
        // Sin esta copia, subir el precio de una plancha cambiaría todos los
        // presupuestos ya entregados.
        $p = armar([4250, 18990, 45900]);
        $linea = $p->lineas->first();

        expect($linea->nombre_material)->toBe('Pino 2x3 seco cepillado 3.20 m')
            ->and($linea->unidad_venta)->toBe('tira 3.20 m')
            ->and((float) $linea->precio_unitario)->toBe(4250.0)
            ->and($linea->origen)->toBe(PresupuestoLinea::ORIGEN_ESTRUCTURA)
            ->and($linea->detalle)->toHaveKey('corte');
    });

    it('redondea el total a los decimales que la moneda admite', function () {
        // El peso chileno no acepta fracción: un total con centavos no se puede
        // pagar.
        $p = armar([4250.55, 0, 0]);

        expect((float) $p->total)->toBe(round(76 * 4250.55))
            ->and(fmod((float) $p->total, 1.0))->toBe(0.0);
    });
});

describe('precios en otra moneda', function () {
    it('convierte al destino pasando por el dólar', function () {
        (new MonedaSeeder)->run();
        TasaCambio::create(['moneda' => 'CLP', 'tasa' => 950, 'fecha' => Carbon::today(), 'fuente' => 'test']);
        TasaCambio::create(['moneda' => 'PEN', 'tasa' => 3.8, 'fecha' => Carbon::today(), 'fuente' => 'test']);

        // Un sol son 950/3,8 = 250 pesos.
        expect(app(TasaCambioService::class)->entre('PEN', 'CLP'))->toBe(250.0)
            ->and(app(TasaCambioService::class)->entre('USD', 'CLP'))->toBe(950.0)
            ->and(app(TasaCambioService::class)->entre('CLP', 'CLP'))->toBe(1.0);
    });

    it('guarda la tasa resuelta en la línea, para que el subtotal sea reproducible', function () {
        (new MonedaSeeder)->run();
        TasaCambio::create(['moneda' => 'CLP', 'tasa' => 950, 'fecha' => Carbon::today(), 'fuente' => 'test']);

        $proyecto = proyectoDePrueba(conCapas());
        $claves = app(CalculoProyectoService::class)->para($proyecto)->claves();

        $p = app(ArmarPresupuestoService::class)->para(
            $proyecto,
            array_combine($claves, [['precio' => 10, 'moneda' => 'USD'], 0, 0]),
            'CLP',
        );

        $madera = $p->lineas->first();

        expect((float) $madera->tasa_cambio)->toBe(950.0)
            ->and($madera->moneda_origen)->toBe('USD')
            // 76 tiras x USD 10 x 950.
            ->and((float) $madera->subtotal)->toBe(722000.0);
    });

    it('avisa cuando no hay tasa para la fecha', function () {
        (new MonedaSeeder)->run();

        $proyecto = proyectoDePrueba(conCapas());
        $claves = app(CalculoProyectoService::class)->para($proyecto)->claves();

        app(ArmarPresupuestoService::class)->para(
            $proyecto,
            array_combine($claves, [['precio' => 10, 'moneda' => 'USD'], 0, 0]),
            'CLP',
        );
    })->throws(UnprocessableEntityHttpException::class, 'No hay tasa de cambio de CLP');
});

describe('precios que no cierran', function () {
    it('avisa qué material quedó sin precio, por su nombre', function () {
        armar([4250]);
    })->throws(UnprocessableEntityHttpException::class, 'Faltan precios para: OSB 11,1 mm, Membrana hidrófuga');

    it('rechaza un formulario armado contra otra versión del proyecto', function () {
        (new MonedaSeeder)->run();
        $proyecto = proyectoDePrueba(conCapas());
        $precios = array_fill_keys(app(CalculoProyectoService::class)->para($proyecto)->claves(), 1);

        app(ArmarPresupuestoService::class)->para($proyecto, [...$precios, 'capa_borrada' => 1], 'CLP');
    })->throws(UnprocessableEntityHttpException::class, 'formulario quedo desactualizado');

    it('rechaza precios negativos', function () {
        armar([-1, 1, 1]);
    })->throws(UnprocessableEntityHttpException::class, 'no puede ser negativo');

    it('rechaza precios que no son números', function () {
        armar(['gratis', 1, 1]);
    })->throws(UnprocessableEntityHttpException::class, 'no es un numero');
});
