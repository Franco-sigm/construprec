<?php

use App\Models\Escuadria;
use App\Models\ProductoCapa;
use Database\Seeders\EscuadriaSeeder;
use Database\Seeders\ProductoCapaSeeder;

beforeEach(function () {
    (new EscuadriaSeeder)->run();
    (new ProductoCapaSeeder)->run();
});

function escuadria2x3(): Escuadria
{
    return Escuadria::where('nominal', '2x3')->where('estado', 'seco_cepillado')->firstOrFail();
}

/**
 * Cuerpo de referencia: planta de 6 x 4, una puerta y dos ventanas que suman
 * 4,0 m2 de vanos.
 *
 * `caras` se reemplaza entero y no se fusiona. array_replace_recursive mezcla
 * arreglo con arreglo por índice, así que pasar una cara con un vano dejaba
 * colgando los vanos de la cara original y la superficie no cuadraba.
 */
function cuerpo(array $sobrescribir = []): array
{
    $caras = $sobrescribir['caras'] ?? null;
    unset($sobrescribir['caras']);

    $base = array_replace_recursive([
        'caras' => [
            ['nombre' => 'Cara 1', 'largo' => 6, 'alto' => 2.4, 'unidad' => 'm', 'vanos' => [
                ['tipo' => 'puerta', 'ancho' => 0.9, 'alto' => 2.0],
                ['tipo' => 'ventana', 'ancho' => 1.2, 'alto' => 1.0, 'antepecho' => 0.9],
            ]],
            ['nombre' => 'Cara 2', 'largo' => 4, 'alto' => 2.4, 'unidad' => 'm', 'vanos' => [
                ['tipo' => 'ventana', 'ancho' => 1.0, 'alto' => 1.0, 'antepecho' => 0.9],
            ]],
            ['nombre' => 'Cara 3', 'largo' => 6, 'alto' => 2.4, 'unidad' => 'm'],
            ['nombre' => 'Cara 4', 'largo' => 4, 'alto' => 2.4, 'unidad' => 'm'],
        ],
        'tabiqueria' => [
            'escuadria_id' => escuadria2x3()->id,
            'largo_comercial_mm' => 3200,
            'separacion' => 0.4,
            'separacion_unidad' => 'm',
            'filas_cadenetas' => 1,
            'merma_pct' => 5,
        ],
    ], $sobrescribir);

    if ($caras !== null) {
        $base['caras'] = $caras;
    }

    return $base;
}

describe('el catálogo', function () {
    it('entrega escuadrías y productos agrupados por tipo de capa', function () {
        $r = $this->getJson('/api/catalogo');

        $r->assertOk()
            ->assertJsonStructure([
                'escuadrias' => [['id', 'nominal', 'descripcion', 'ancho_real_mm', 'largos_comerciales_mm']],
                'productos' => ['arriostramiento', 'rev_interior', 'rev_exterior', 'aislante', 'membrana'],
                'tipos_capa' => [['valor', 'etiqueta', 'descuenta_vanos_por_defecto']],
            ]);
    });

    it('ofrece alternativas para el revestimiento interior, no sólo yeso-cartón', function () {
        $interior = collect($this->getJson('/api/catalogo')->json('productos.rev_interior'));

        expect($interior->count())->toBeGreaterThan(1)
            ->and($interior->pluck('nombre')->filter(fn ($n) => str_contains($n, 'Terciado ranurado')))
            ->not->toBeEmpty();
    });

    it('expone el ancho útil del siding, para poder explicar su rendimiento', function () {
        $siding = collect($this->getJson('/api/catalogo')->json('productos.rev_exterior'))
            ->firstWhere('nombre', 'Siding fibrocemento 190 x 3660 x 6 mm');

        expect($siding['ancho_mm'])->toBe(190)
            ->and($siding['ancho_util_mm'])->toBe(160)
            ->and((float) $siding['rendimiento_m2'])->toBe(0.5856);
    });

    it('no exige token: son medidas que cualquiera lee en la barraca', function () {
        $this->getJson('/api/catalogo')->assertOk();
    });
});

describe('el cálculo', function () {
    it('devuelve la lista de materiales sin guardar nada', function () {
        $r = $this->postJson('/api/calculos', cuerpo());

        $r->assertOk()
            ->assertJsonPath('obra.superficie_bruta_m2', fn ($v) => (float) $v === 48.0)
            ->assertJsonPath('obra.superficie_neta_m2', fn ($v) => (float) $v === 44.0)
            ->assertJsonPath('corte.tiras_a_comprar', 76);

        expect($r->json('materiales.0.clave'))->toBe('madera')
            ->and((float) $r->json('materiales.0.cantidad_comprar'))->toBe(76.0);

        // Es una consulta pura: no puede haber quedado nada escrito.
        $this->assertDatabaseCount('proyectos', 0);
        $this->assertDatabaseCount('presupuestos', 0);
    });

    it('agrega una línea por cada producto elegido del catálogo', function () {
        $osb = ProductoCapa::where('nombre', 'like', 'OSB estructural 11,1%')->firstOrFail();
        $siding = ProductoCapa::where('nombre', 'like', 'Siding fibrocemento 190 x 3660 x 6%')->firstOrFail();

        $r = $this->postJson('/api/calculos', cuerpo(['capas' => [
            ['producto_capa_id' => $osb->id, 'aplicacion' => 'exterior'],
            ['producto_capa_id' => $siding->id, 'aplicacion' => 'exterior'],
        ]]));

        $r->assertOk();

        expect($r->json('materiales'))->toHaveCount(3)
            ->and((float) $r->json('materiales.1.cantidad_comprar'))->toBe(16.0)
            // El siding va traslapado: 83 tablas, no 16 planchas.
            ->and((float) $r->json('materiales.2.cantidad_comprar'))->toBe(83.0)
            ->and($r->json('materiales.2.unidad_venta'))->toBe('tabla');
    });

    it('acepta la separación en pulgadas aunque los muros vayan en metros', function () {
        // Es la regla central del producto y la que se rompía con un tope
        // numérico fijo: "16" son 16 pulgadas, no 16 metros.
        $r = $this->postJson('/api/calculos', cuerpo([
            'tabiqueria' => ['separacion' => 16, 'separacion_unidad' => 'in'],
        ]));

        $r->assertOk();
        expect((float) $r->json('obra.superficie_neta_m2'))->toBe(44.0);
    });

    it('cambia el resultado al cambiar las filas de cadenetas', function () {
        $sin = $this->postJson('/api/calculos', cuerpo(['tabiqueria' => ['filas_cadenetas' => 0]]));
        $con = $this->postJson('/api/calculos', cuerpo(['tabiqueria' => ['filas_cadenetas' => 2]]));

        // Más cadenetas es más madera, pero sale del recorte: no cuesta tiras.
        expect($con->json('obra.metros_lineales_madera'))
            ->toBeGreaterThan($sin->json('obra.metros_lineales_madera'))
            ->and($con->json('corte.tiras_a_comprar'))->toBe($sin->json('corte.tiras_a_comprar'));
    });

    it('cambia el conteo al cambiar la separación', function () {
        $cerca = $this->postJson('/api/calculos', cuerpo(['tabiqueria' => ['separacion' => 0.4]]));
        $lejos = $this->postJson('/api/calculos', cuerpo(['tabiqueria' => ['separacion' => 0.6]]));

        expect($lejos->json('obra.piezas'))->toBeLessThan($cerca->json('obra.piezas'));
    });
});

describe('entradas que no cierran', function () {
    it('rechaza una separación absurda con un mensaje en español', function () {
        $r = $this->postJson('/api/calculos', cuerpo(['tabiqueria' => ['separacion' => 9, 'separacion_unidad' => 'm']]));

        $r->assertStatus(422)->assertJsonValidationErrors('tabiqueria.separacion');

        $mensaje = $r->json('errors')['tabiqueria.separacion'][0];
        expect(str_contains($mensaje, 'entre 10 cm y 2 m'))->toBeTrue();
    });

    it('rechaza un producto que no está en el catálogo', function () {
        $this->postJson('/api/calculos', cuerpo(['capas' => [['producto_capa_id' => 99999]]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('capas.0.producto_capa_id');
    });

    it('exige al menos una cara', function () {
        $this->postJson('/api/calculos', [...cuerpo(), 'caras' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('caras');
    });

    it('devuelve 422 y no 500 cuando la geometría no cierra', function () {
        // Un vano más ancho que su cara: el servicio lo rechaza con un mensaje
        // que el usuario puede entender, no con un error del servidor.
        $this->postJson('/api/calculos', cuerpo(['caras' => [
            ['largo' => 2, 'alto' => 2.4, 'unidad' => 'm', 'vanos' => [
                ['tipo' => 'puerta', 'ancho' => 3, 'alto' => 2],
            ]],
        ]]))->assertStatus(422);
    });
});

describe('encaje de los vanos con la trama', function () {
    it('devuelve los anchos que calzan con los pies derechos', function () {
        $r = $this->postJson('/api/calculos', cuerpo());

        // Con 40 cm de separación y un 2x3 de 41 mm: 400k - 123.
        expect($r->json('tabiqueria.anchos_modulares_mm'))->toContain(677, 1077, 1477)
            ->and($r->json('tabiqueria.separacion_mm'))->toBe(400)
            ->and($r->json('tabiqueria.espesor_pieza_mm'))->toBe(41);
    });

    it('avisa qué vano no calza y cuál sería el ancho más cercano', function () {
        $r = $this->postJson('/api/calculos', cuerpo());
        $puerta = $r->json('caras.0.vanos.0');

        // Una puerta de 900 ocupa 2,56 tramos: sobra medio tramo al lado.
        expect($puerta['calza_con_la_trama'])->toBeFalse()
            ->and($puerta['ancho_sugerido_mm'])->toBe(1077)
            ->and($puerta['tramos_que_ocupa'])->toBeGreaterThan(2.5)
            ->and($puerta['tramos_que_ocupa'])->toBeLessThan(2.6);
    });

    it('confirma el que sí calza', function () {
        $r = $this->postJson('/api/calculos', cuerpo(['caras' => [
            ['largo' => 6, 'alto' => 2.4, 'unidad' => 'm', 'vanos' => [
                ['tipo' => 'ventana', 'ancho' => 1.077, 'alto' => 1.0, 'antepecho' => 0.9],
            ]],
        ]]));

        $vano = $r->json('caras.0.vanos.0');

        expect($vano['calza_con_la_trama'])->toBeTrue()
            ->and((float) $vano['tramos_que_ocupa'])->toBe(3.0);
    });

    it('los anchos cambian con la separación y con la escuadría', function () {
        $a60 = $this->postJson('/api/calculos', cuerpo(['tabiqueria' => ['separacion' => 0.6]]));

        // 600k - 123: 477, 1077, 1677...
        expect($a60->json('tabiqueria.anchos_modulares_mm'))->toContain(477, 1077, 1677);
    });

    it('devuelve la cantidad de cada vano, para poder dibujarlos', function () {
        $r = $this->postJson('/api/calculos', cuerpo(['caras' => [
            ['largo' => 8, 'alto' => 2.4, 'unidad' => 'm', 'vanos' => [
                ['tipo' => 'ventana', 'ancho' => 1.0, 'alto' => 1.0, 'antepecho' => 0.9, 'cantidad' => 3],
            ]],
        ]]));

        expect($r->json('caras.0.vanos.0.cantidad'))->toBe(3)
            // Tres ventanas de 1 m2 descuentan 3 m2 de los 19,2 del muro.
            ->and((float) $r->json('obra.superficie_vanos_m2'))->toBe(3.0);
    });
});
