<?php

use App\Models\Presupuesto;
use App\Models\ProyectoCapa;
use App\Models\User;
use Database\Seeders\EscuadriaSeeder;
use Database\Seeders\MonedaSeeder;
use Database\Seeders\ProductoCapaSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    (new EscuadriaSeeder)->run();
    (new ProductoCapaSeeder)->run();
    (new MonedaSeeder)->run();
    Sanctum::actingAs(User::factory()->create());
});

/**
 * Crea un proyecto y devuelve su id junto a las claves de sus materiales.
 *
 * Las claves las arma el cálculo y llevan el id de la fila de la capa, no el del
 * producto del catálogo: un proyecto puede tener dos capas del mismo producto en
 * aplicaciones distintas y el formulario tiene que poder separarlas.
 */
function proyectoGuardado(): array
{
    $id = test()->postJson('/api/proyectos', payloadProyecto())->json('id');
    $capa = ProyectoCapa::where('proyecto_id', $id)->firstOrFail();

    return [$id, ['madera' => 4250, "capa_{$capa->id}" => 18990]];
}

describe('emitir el presupuesto', function () {
    it('avisa si falta el precio de algún material, en vez de sumar cero', function () {
        [$id] = proyectoGuardado();

        $r = $this->postJson("/api/proyectos/{$id}/presupuestos", [
            'precios' => ['madera' => 4250],
            'moneda' => 'CLP',
        ]);

        $r->assertStatus(422);
        expect($r->json('message'))->toContain('Faltan precios');
    });

    it('guarda el presupuesto completo con todos los precios', function () {
        [$id, $precios] = proyectoGuardado();

        $r = $this->postJson("/api/proyectos/{$id}/presupuestos", compact('precios') + ['moneda' => 'CLP']);

        $r->assertStatus(201);

        expect($r->json('lineas'))->toHaveCount(2)
            ->and($r->json('estado'))->toBe(Presupuesto::BORRADOR);

        $this->assertDatabaseCount('presupuestos', 1);
        $this->assertDatabaseCount('presupuesto_lineas', 2);
    });

    it('cada línea guarda de dónde salió su cantidad', function () {
        [$id] = proyectoGuardado();
        $capaId = ProyectoCapa::where('proyecto_id', $id)->firstOrFail()->id;

        $r = $this->postJson("/api/proyectos/{$id}/presupuestos", [
            'precios' => ['madera' => 4250, "capa_{$capaId}" => 18990],
            'moneda' => 'CLP',
        ]);

        $osb = collect($r->json('lineas'))->firstWhere('unidad_venta', 'plancha');

        // 48 m2 de muro menos los 3,0 de la puerta y la ventana de la cara 1.
        expect((float) $osb['magnitud'])->toBe(45.0)
            ->and($osb['unidad_magnitud'])->toBe('m2')
            ->and((float) $osb['precio_unitario'])->toBe(18990.0)
            // Se paga lo que se compra, no lo que se ocupa.
            ->and((float) $osb['subtotal'])->toBe((float) $osb['cantidad_comprar'] * 18990.0);
    });

    it('emitirlo lo vuelve un documento histórico', function () {
        [$id] = proyectoGuardado();
        $capaId = ProyectoCapa::where('proyecto_id', $id)->firstOrFail()->id;

        $presupuesto = $this->postJson("/api/proyectos/{$id}/presupuestos", [
            'precios' => ['madera' => 4250, "capa_{$capaId}" => 18990],
            'moneda' => 'CLP',
        ])->json();

        $r = $this->postJson("/api/proyectos/{$id}/presupuestos/{$presupuesto['id']}/emitir");

        $r->assertOk();
        expect($r->json('estado'))->toBe(Presupuesto::EMITIDO);
    });

    it('cada cálculo emite uno nuevo en vez de pisar el anterior', function () {
        // Son fotografías: el que el cliente ya tiene en la mano no puede cambiar
        // porque hoy se cotizó distinto.
        [$id] = proyectoGuardado();
        $capaId = ProyectoCapa::where('proyecto_id', $id)->firstOrFail()->id;

        foreach ([4250, 4600] as $precio) {
            $this->postJson("/api/proyectos/{$id}/presupuestos", [
                'precios' => ['madera' => $precio, "capa_{$capaId}" => 18990],
                'moneda' => 'CLP',
            ])->assertStatus(201);
        }

        expect($this->getJson("/api/proyectos/{$id}/presupuestos")->json('presupuestos'))->toHaveCount(2);
        $this->assertDatabaseCount('presupuestos', 2);
    });

    it('rechaza una moneda que no existe', function () {
        [$id] = proyectoGuardado();

        $this->postJson("/api/proyectos/{$id}/presupuestos", [
            'precios' => ['madera' => 4250],
            'moneda' => 'XXX',
        ])->assertStatus(422)->assertJsonValidationErrors('moneda');
    });

    it('no deja emitir sobre el proyecto de otro', function () {
        [$id] = proyectoGuardado();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/proyectos/{$id}/presupuestos", [
            'precios' => ['madera' => 4250],
            'moneda' => 'CLP',
        ])->assertStatus(404);
    });
});
