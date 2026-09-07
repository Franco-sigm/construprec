<?php

use App\Models\CaraVano;
use App\Models\Escuadria;
use App\Models\Proyecto;
use App\Models\ProyectoCapa;
use App\Models\ProyectoCara;
use App\Models\TabiqueriaConfig;
use App\Models\User;
use App\Services\Capas\AplicacionCapa;
use App\Services\Capas\TipoCapa;
use App\Services\Presupuesto\CalculoProyectoService;
use App\Services\Tabiqueria\TipoVano;
use Database\Seeders\EscuadriaSeeder;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Arma el mismo proyecto con el que se verifico el motor a mano: planta de
 * 6 x 4 m, muros de 2,40, 2x3 seco cepillado a 40 cm, una puerta y dos ventanas.
 */
function proyectoDePrueba(array $capas = []): Proyecto
{
    (new EscuadriaSeeder)->run();

    $escuadria = Escuadria::where('nominal', '2x3')->where('estado', 'seco_cepillado')->firstOrFail();
    $user = User::factory()->create();

    $proyecto = Proyecto::create([
        'user_id' => $user->id,
        'nombre' => 'Ampliación',
        'ancho_mm' => 4000,
        'largo_mm' => 6000,
        'alto_mm' => 2400,
        'unidad_ingreso' => 'm',
    ]);

    TabiqueriaConfig::create([
        'proyecto_id' => $proyecto->id,
        'escuadria_id' => $escuadria->id,
        'escuadria_ancho_mm' => $escuadria->ancho_real_mm,
        'escuadria_alto_mm' => $escuadria->alto_real_mm,
        'largo_comercial_mm' => 3200,
        'separacion_mm' => 400,
        'soleras_superiores' => 1,
        'merma_pct' => 5,
        'filas_cadenetas' => 1,
    ]);

    foreach ($proyecto->carasDeLaPlanta() as $datos) {
        $cara = ProyectoCara::create([...$datos, 'proyecto_id' => $proyecto->id]);

        if ($cara->orden === 1) {
            CaraVano::create(['cara_id' => $cara->id, 'tipo' => TipoVano::Puerta, 'ancho_mm' => 900, 'alto_mm' => 2000]);
            CaraVano::create(['cara_id' => $cara->id, 'tipo' => TipoVano::Ventana, 'ancho_mm' => 1200, 'alto_mm' => 1000, 'antepecho_mm' => 900]);
        }

        if ($cara->orden === 2) {
            CaraVano::create(['cara_id' => $cara->id, 'tipo' => TipoVano::Ventana, 'ancho_mm' => 1000, 'alto_mm' => 1000, 'antepecho_mm' => 900]);
        }
    }

    foreach ($capas as $orden => $capa) {
        ProyectoCapa::create([...$capa, 'proyecto_id' => $proyecto->id, 'orden' => $orden]);
    }

    return $proyecto->fresh();
}

function calcular(Proyecto $proyecto)
{
    return app(CalculoProyectoService::class)->para($proyecto);
}

it('deduce la madera como un solo material, no como siete', function () {
    // En la barraca se compra un producto y se paga un precio, aunque de la
    // escuadria salgan pies derechos, soleras, jambas, dinteles y cadenetas.
    $calculo = calcular(proyectoDePrueba());

    expect($calculo->materiales)->toHaveCount(1)
        ->and($calculo->materiales[0]->nombre)->toBe('Pino 2x3 seco cepillado 3.20 m')
        ->and($calculo->materiales[0]->unidadVenta)->toBe('tira 3.20 m')
        ->and($calculo->materiales[0]->esEstructura())->toBeTrue();
});

it('llega a las mismas cantidades que el cálculo verificado a mano', function () {
    $calculo = calcular(proyectoDePrueba());

    expect($calculo->despiece->superficieBrutaM2)->toBe(48.0)
        ->and($calculo->despiece->superficieVanosM2)->toBe(4.0)
        ->and($calculo->despiece->superficieNetaM2())->toBe(44.0)
        ->and($calculo->planCorte->tirasNetas())->toBe(72)
        // 72 tiras con 5% de merma.
        ->and($calculo->materiales[0]->cantidadComprar)->toBe(76.0);
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

    expect($calculo->materiales)->toHaveCount(3)
        ->and($calculo->claves())->toBe(['madera', 'capa_1', 'capa_2'])
        // El OSB descuenta vanos: 44 m2. La membrana no: 48 m2.
        ->and($calculo->material('capa_1')->magnitud)->toBe(44.0)
        ->and($calculo->material('capa_2')->magnitud)->toBe(48.0)
        ->and($calculo->material('capa_1')->cantidadComprar)->toBe(16.0)
        ->and($calculo->material('capa_2')->cantidadComprar)->toBe(1.0);
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
