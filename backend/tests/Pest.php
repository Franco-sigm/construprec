<?php

use App\Models\CaraVano;
use App\Models\Escuadria;
use App\Models\Proyecto;
use App\Models\ProyectoCapa;
use App\Models\ProyectoCara;
use App\Models\TabiqueriaConfig;
use App\Models\User;
use App\Services\Presupuesto\CalculoProyectoService;
use App\Services\Tabiqueria\TipoVano;
use Database\Seeders\EscuadriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

// Los tests de Feature tocan la base, asi que cada uno arranca con el esquema
// recien migrado. Corren contra SQLite en memoria (phpunit.xml), que es rapido
// pero no se comporta igual que MySQL en todo: conviene una corrida contra
// MySQL antes de desplegar.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

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
