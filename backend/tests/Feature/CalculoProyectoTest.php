<?php

use App\Services\Capas\AplicacionCapa;
use App\Services\Capas\TipoCapa;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

it('deduce la madera como un solo material, no como siete', function () {
    // En la barraca se compra un producto y se paga un precio, aunque de la
    // escuadria salgan pies derechos, soleras, jambas, dinteles y cadenetas.
    $calculo = calcular(proyectoDePrueba());

    expect($calculo->materiales)->toHaveCount(1)
        ->and($calculo->materiales[0]->nombre)->toBe('Pino 2x3 seco cepillado 3,20 m')
        ->and($calculo->materiales[0]->unidadVenta)->toBe('tira')
        ->and($calculo->materiales[0]->esEstructura())->toBeTrue();
});

it('llega a las mismas cantidades que el cálculo verificado a mano', function () {
    $calculo = calcular(proyectoDePrueba());

    expect($calculo->despiece->superficieBrutaM2)->toBe(48.0)
        ->and($calculo->despiece->superficieVanosM2)->toBe(4.0)
        ->and($calculo->despiece->superficieNetaM2())->toBe(44.0)
        // 72 del despiece de las caras más 4 postes de esquina, uno por
        // encuentro: cada uno ocupa una tira propia porque mide 2,318 y de una
        // tira de 3,2 no salen dos.
        ->and($calculo->planCorte->tirasNetas())->toBe(76)
        // 76 tiras con 5% de descarte.
        ->and($calculo->materiales[0]->cantidadComprar)->toBe(80.0);
});

it('guarda el desglose completo para poder auditar la cantidad', function () {
    $detalle = calcular(proyectoDePrueba())->materiales[0]->detalle;

    expect($detalle)->toHaveKeys(['superficie_neta_m2', 'cavidad_m2', 'piezas', 'corte'])
        ->and($detalle['corte'])->toHaveKeys(['tiras_a_comprar', 'plan'])
        // El plan de corte dice qué sale de cada tira.
        ->and($detalle['corte']['plan'][0]['cortes'])->not->toBeEmpty();
});

it('agrega una línea por cada capa de recubrimiento', function () {
    $calculo = calcular(proyectoDePrueba([
        ['tipo' => TipoCapa::Arriostramiento, 'aplicacion' => AplicacionCapa::Exterior, 'nombre' => 'OSB 11,1 mm',
            'unidad_venta' => 'plancha', 'rendimiento' => 2.9768, 'unidad_rendimiento' => 'm2', 'merma_pct' => 5],
        ['tipo' => TipoCapa::Membrana, 'aplicacion' => AplicacionCapa::Exterior, 'nombre' => 'Membrana hidrófuga',
            'unidad_venta' => 'rollo', 'rendimiento' => 75.0, 'unidad_rendimiento' => 'm2', 'merma_pct' => 10],
    ]));

    [$madera, $osb, $membrana] = $calculo->claves();

    expect($calculo->materiales)->toHaveCount(3)
        ->and($madera)->toBe('madera')
        // La clave de una capa lleva su id, no su posicion: un proyecto puede
        // tener dos capas del mismo tipo y hay que poder distinguirlas.
        ->and($osb)->toStartWith('capa_')
        ->and($membrana)->toStartWith('capa_')
        ->and($osb)->not->toBe($membrana)
        // El OSB descuenta vanos: 44 m2. La membrana no: 48 m2.
        ->and($calculo->material($osb)->magnitud)->toBe(44.0)
        ->and($calculo->material($membrana)->magnitud)->toBe(48.0)
        ->and($calculo->material($osb)->cantidadComprar)->toBe(16.0)
        ->and($calculo->material($membrana)->cantidadComprar)->toBe(1.0);
});

it('entrega el formulario de precios con los nombres y sin precios', function () {
    // Es la pantalla donde el usuario escribe los precios uno por uno: las
    // cantidades ya están resueltas porque no dependen del precio.
    $formulario = calcular(proyectoDePrueba())->paraFormulario();

    expect($formulario[0])->toHaveKeys(['clave', 'nombre', 'unidad_venta', 'cantidad_comprar'])
        ->and($formulario[0])->not->toHaveKey('precio');
});

it('avisa cuando falta configurar la tabiquería', function () {
    $proyecto = proyectoDePrueba();
    $proyecto->tabiqueria->delete();

    calcular($proyecto->fresh());
})->throws(UnprocessableEntityHttpException::class, 'no tiene configurada la tabiqueria');

it('avisa cuando el proyecto no tiene caras', function () {
    $proyecto = proyectoDePrueba();
    $proyecto->caras()->delete();

    calcular($proyecto->fresh());
})->throws(UnprocessableEntityHttpException::class, 'no tiene ninguna cara');
