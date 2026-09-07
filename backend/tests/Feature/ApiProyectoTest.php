<?php

use App\Models\User;
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
