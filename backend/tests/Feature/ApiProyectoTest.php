<?php

use App\Models\Escuadria;
use App\Models\ProductoCapa;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Presupuesto\CalculoProyectoService;
use Database\Seeders\EscuadriaSeeder;
use Database\Seeders\MonedaSeeder;
use Database\Seeders\ProductoCapaSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    (new EscuadriaSeeder)->run();
    (new ProductoCapaSeeder)->run();
    (new MonedaSeeder)->run();
});

describe('entrar', function () {
    it('entrega un token con las credenciales correctas', function () {
        $usuario = User::factory()->create(['email' => 'franco@construprec.cl', 'password' => bcrypt('secreta123')]);

        $r = $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'secreta123']);

        $r->assertOk()->assertJsonStructure(['token', 'usuario' => ['id', 'nombre', 'email']]);
        expect($r->json('token'))->not->toBeEmpty();
    });

    it('no distingue entre correo inexistente y contraseña equivocada', function () {
        // Mensajes distintos dejarían averiguar qué correos están registrados
        // probando de a uno.
        User::factory()->create(['email' => 'franco@construprec.cl', 'password' => bcrypt('secreta123')]);

        $malaClave = $this->postJson('/api/login', ['email' => 'franco@construprec.cl', 'password' => 'otra']);
        $sinUsuario = $this->postJson('/api/login', ['email' => 'nadie@construprec.cl', 'password' => 'otra']);

        expect($malaClave->json('errors.email.0'))->toBe($sinUsuario->json('errors.email.0'));
    });

    it('cierra sólo la sesión de este dispositivo', function () {
        $usuario = User::factory()->create();
        $otro = $usuario->createToken('celular')->plainTextToken;
        Sanctum::actingAs($usuario);

        $this->postJson('/api/logout')->assertOk();

        // El token del otro dispositivo sigue vivo.
        expect($usuario->tokens()->count())->toBe(1);
        expect($otro)->not->toBeEmpty();
    });
});

describe('guardar el proyecto', function () {
    it('exige token', function () {
        $this->postJson('/api/proyectos', payloadProyecto())->assertStatus(401);
    });

    it('guarda la geometría completa', function () {
        Sanctum::actingAs(User::factory()->create());

        $r = $this->postJson('/api/proyectos', payloadProyecto());

        $r->assertStatus(201);

        $this->assertDatabaseCount('proyectos', 1);
        $this->assertDatabaseCount('proyecto_caras', 4);
        $this->assertDatabaseCount('cara_vanos', 2);
        $this->assertDatabaseCount('tabiqueria_configs', 1);
        $this->assertDatabaseCount('proyecto_capas', 1);
    });

    it('convierte las medidas a milímetros al guardar', function () {
        Sanctum::actingAs(User::factory()->create());

        // Una cara escrita en pies tiene que quedar guardada igual que en metros.
        $this->postJson('/api/proyectos', payloadProyecto(['caras' => [
            ['nombre' => 'En pies', 'largo' => 10, 'alto' => 8, 'unidad' => 'ft'],
        ]]))->assertStatus(201);

        $this->assertDatabaseHas('proyecto_caras', ['largo_mm' => 3048, 'alto_mm' => 2438]);
    });

    it('copia las medidas de la escuadría, no sólo su id', function () {
        // Si mañana se corrige el catálogo, un proyecto guardado no puede cambiar
        // de dimensiones por debajo.
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/proyectos', payloadProyecto());

        $this->assertDatabaseHas('tabiqueria_configs', [
            'escuadria_ancho_mm' => 41,
            'escuadria_alto_mm' => 65,
        ]);
    });

    it('copia nombre y rendimiento de la capa desde el catálogo', function () {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/proyectos', payloadProyecto());

        $this->assertDatabaseHas('proyecto_capas', [
            'nombre' => 'OSB estructural 11,1 mm 1,22 x 2,44',
            'rendimiento' => '2.9768',
        ]);
    });

    it('exige un nombre', function () {
        Sanctum::actingAs(User::factory()->create());
        $datos = payloadProyecto();
        unset($datos['nombre']);

        $this->postJson('/api/proyectos', $datos)
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    });
});

describe('volver a abrirlo', function () {
    it('devuelve el proyecto en la forma que espera el asistente', function () {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/proyectos', payloadProyecto())->json('id');

        $r = $this->getJson("/api/proyectos/{$id}");

        $r->assertOk()->assertJsonStructure([
            'id', 'nombre',
            'planta' => ['ancho_mm', 'largo_mm', 'alto_mm'],
            'tabiqueria' => ['escuadria_id', 'separacion_mm', 'filas_cadenetas'],
            'caras' => [['nombre', 'largo_mm', 'alto_mm', 'vanos']],
            'capas' => [['producto_capa_id', 'nombre']],
        ]);

        expect($r->json('caras'))->toHaveCount(4)
            ->and($r->json('caras.0.vanos'))->toHaveCount(2);
    });

    it('lista los proyectos del usuario', function () {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/proyectos', payloadProyecto(['nombre' => 'Uno']));
        $this->postJson('/api/proyectos', payloadProyecto(['nombre' => 'Dos']));

        expect($this->getJson('/api/proyectos')->json('proyectos'))->toHaveCount(2);
    });
});

describe('los proyectos son de quien los hizo', function () {
    it('no muestra el de otro, y responde 404 en vez de 403', function () {
        // 403 confirmaría que ese identificador existe. 404 no dice nada.
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/proyectos', payloadProyecto())->json('id');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/proyectos/{$id}")->assertStatus(404);

        // Sigue existiendo: no se ocultó borrándolo, se ocultó al otro usuario.
        $this->assertDatabaseCount('proyectos', 1);
    });

    it('no deja actualizar ni borrar el de otro', function () {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/proyectos', payloadProyecto())->json('id');

        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/proyectos/{$id}", payloadProyecto())->assertStatus(404);
        $this->deleteJson("/api/proyectos/{$id}")->assertStatus(404);

        $this->assertDatabaseCount('proyectos', 1);
    });

    it('sólo lista los propios', function () {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/proyectos', payloadProyecto(['nombre' => 'Mío']));

        Sanctum::actingAs(User::factory()->create());
        expect($this->getJson('/api/proyectos')->json('proyectos'))->toBeEmpty();
    });
});

describe('actualizar', function () {
    it('rehace los hijos en vez de dejar huérfanos', function () {
        // El asistente permite quitar vanos libremente, así que actualizar borra
        // y vuelve a crear: si no, un vano de una versión anterior sobreviviría.
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/proyectos', payloadProyecto())->json('id');

        $this->putJson("/api/proyectos/{$id}", payloadProyecto([
            'nombre' => 'Sin ventanas',
            'caras' => [['nombre' => 'Única', 'largo' => 5, 'alto' => 2.4, 'unidad' => 'm']],
        ]))->assertOk();

        $this->assertDatabaseCount('proyecto_caras', 1);
        $this->assertDatabaseCount('cara_vanos', 0);
        $this->assertDatabaseCount('tabiqueria_configs', 1);
        $this->assertDatabaseHas('proyectos', ['id' => $id, 'nombre' => 'Sin ventanas']);
    });
});

describe('la techumbre también se guarda', function () {
    function conTechumbre(): array
    {
        $cercha = Escuadria::where('nominal', '3x4')->where('estado', 'seco_cepillado')->firstOrFail();
        $zinc = ProductoCapa::where('nombre', 'like', 'Zinc acanalado%3,66%')->firstOrFail();

        return payloadProyecto(['techumbre' => [
            'aguas' => 2, 'luz' => 4, 'largo' => 6, 'altura_cumbrera' => 1, 'alero' => 0.5,
            'unidad' => 'm', 'escuadria_id' => $cercha->id, 'largo_comercial_mm' => 4000,
            'separacion_cerchas' => 1, 'separacion_costaneras' => 1.1, 'merma_pct' => 5,
            'capas' => [['producto_capa_id' => $zinc->id]],
        ]]);
    }

    it('un proyecto puede quedarse sin techo', function () {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/proyectos', payloadProyecto())->assertStatus(201);

        $this->assertDatabaseCount('techumbre_configs', 0);
    });

    it('guarda la techumbre y su cubierta', function () {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/proyectos', conTechumbre())->assertStatus(201);

        $this->assertDatabaseCount('techumbre_configs', 1);
        $this->assertDatabaseHas('techumbre_configs', [
            'aguas' => 2, 'luz_mm' => 4000, 'altura_cumbrera_mm' => 1000, 'alero_mm' => 500,
        ]);
        // La cubierta va marcada como de techumbre, no mezclada con las del muro.
        $this->assertDatabaseHas('proyecto_capas', ['etapa' => 'techumbre']);
        $this->assertDatabaseHas('proyecto_capas', ['etapa' => 'muros']);
    });

    it('copia la sección real de la cercha, no sólo su id', function () {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/proyectos', conTechumbre());

        $this->assertDatabaseHas('techumbre_configs', [
            'escuadria_ancho_mm' => 65,
            'escuadria_alto_mm' => 90,
        ]);
    });

    it('devuelve la techumbre al reabrir el proyecto', function () {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/proyectos', conTechumbre())->json('id');

        $r = $this->getJson("/api/proyectos/{$id}");

        expect($r->json('techumbre.aguas'))->toBe(2)
            ->and($r->json('techumbre.luz_mm'))->toBe(4000)
            ->and($r->json('techumbre.capas'))->toHaveCount(1)
            // Las capas del muro no traen la cubierta mezclada.
            ->and($r->json('capas'))->toHaveCount(1);
    });

    it('quitar la techumbre la borra en vez de dejarla huérfana', function () {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/proyectos', conTechumbre())->json('id');

        $this->putJson("/api/proyectos/{$id}", payloadProyecto())->assertOk();

        $this->assertDatabaseCount('techumbre_configs', 0);
        $this->assertDatabaseMissing('proyecto_capas', ['etapa' => 'techumbre']);
    });
});

describe('las claves de precio son las mismas al calcular y al emitir', function () {
    /*
     * El formulario de precios se llena con las claves que devuelve el cálculo sin
     * estado, y el presupuesto se emite contra las que produce el proyecto
     * guardado. Si difieren, al emitir parece que faltaran todos los precios
     * aunque estén todos puestos, y el mensaje de error no da ninguna pista.
     */
    it('coinciden exactamente', function () {
        Sanctum::actingAs(User::factory()->create());

        $cuerpo = conTechumbre();
        $id = $this->postJson('/api/proyectos', $cuerpo)->json('id');

        $alCalcular = collect($this->postJson('/api/calculos', $cuerpo)->json('materiales'))
            ->pluck('clave')->sort()->values()->all();

        $alEmitir = app(CalculoProyectoService::class)
            ->para(Proyecto::findOrFail($id))
            ->claves();

        expect(collect($alEmitir)->sort()->values()->all())->toBe($alCalcular);
    });

    it('un presupuesto se emite con los precios que recogió el formulario', function () {
        Sanctum::actingAs(User::factory()->create());

        $cuerpo = conTechumbre();
        $id = $this->postJson('/api/proyectos', $cuerpo)->json('id');

        // Se ponen precios usando las claves del cálculo, como hace la interfaz.
        $precios = collect($this->postJson('/api/calculos', $cuerpo)->json('materiales'))
            ->mapWithKeys(fn ($m) => [$m['clave'] => 1000])
            ->all();

        $r = $this->postJson("/api/proyectos/{$id}/presupuestos", [
            'precios' => $precios,
            'moneda' => 'CLP',
        ]);

        $r->assertStatus(201);
        expect($r->json('lineas'))->toHaveCount(count($precios));
    });
});
