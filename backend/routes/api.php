<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalculoController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\PresupuestoController;
use App\Http\Controllers\Api\ProyectoController;
use Illuminate\Support\Facades\Route;

/*
 * Público: el catálogo son medidas de productos que cualquiera lee en la
 * barraca, y el cálculo no guarda nada ni toca datos de nadie. Exigir un token
 * para probar la aplicación obligaría a registrarse antes de saber si sirve.
 */
Route::get('/catalogo', CatalogoController::class);
Route::post('/calculos', CalculoController::class);

// Tres intentos por minuto: un formulario de entrada es donde se prueban
// contraseñas a la fuerza, y sin freno cualquiera puede intentarlo miles de
// veces por segundo.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:3,1');

/*
 * A partir de acá hace falta token. Los proyectos son de quien los hizo.
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/yo', [AuthController::class, 'yo']);

    Route::get('/proyectos', [ProyectoController::class, 'index']);
    Route::post('/proyectos', [ProyectoController::class, 'store']);
    Route::get('/proyectos/{proyecto}', [ProyectoController::class, 'show']);
    Route::put('/proyectos/{proyecto}', [ProyectoController::class, 'update']);
    Route::delete('/proyectos/{proyecto}', [ProyectoController::class, 'destroy']);

    Route::get('/proyectos/{proyecto}/presupuestos', [PresupuestoController::class, 'index']);
    Route::post('/proyectos/{proyecto}/presupuestos', [PresupuestoController::class, 'store']);
    Route::get('/proyectos/{proyecto}/presupuestos/{presupuesto}', [PresupuestoController::class, 'show']);
    Route::post('/proyectos/{proyecto}/presupuestos/{presupuesto}/emitir', [PresupuestoController::class, 'emitir']);
    Route::get('/proyectos/{proyecto}/presupuestos/{presupuesto}/pdf', [PresupuestoController::class, 'pdf']);
});
