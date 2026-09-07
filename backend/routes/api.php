<?php

use App\Http\Controllers\Api\CalculoController;
use App\Http\Controllers\Api\CatalogoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Rutas públicas: el catálogo son medidas de productos que cualquiera lee en la
 * barraca, y el cálculo no guarda nada ni toca datos de nadie. Exigir un token
 * para probar la aplicación obligaría a registrarse antes de saber si sirve.
 */
Route::get('/catalogo', CatalogoController::class);
Route::post('/calculos', CalculoController::class);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
