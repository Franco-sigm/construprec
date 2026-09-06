<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasas_cambio', function (Blueprint $table) {
            $table->id();

            // Todas las tasas se guardan contra el dolar. Guardar los pares
            // directos (CLP->PEN, CLP->BRL, PEN->BRL...) obligaria a mantener
            // n^2 combinaciones; con USD de pivote son n filas y cualquier par
            // se arma con dos saltos.
            $table->char('moneda', 3);

            // Cuantas unidades de `moneda` equivalen a 1 USD. Ocho decimales
            // porque monedas de valor alto frente al dolar necesitan precision
            // en la division inversa.
            $table->decimal('tasa', 18, 8);

            $table->date('fecha');
            $table->string('fuente', 60);
            $table->timestamps();

            $table->foreign('moneda')->references('codigo')->on('monedas')->restrictOnDelete();

            // Una sola tasa por moneda y dia. Permite que el cron corra varias
            // veces sin duplicar, resolviendo con updateOrCreate sobre esta clave.
            $table->unique(['moneda', 'fecha']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasas_cambio');
    }
};
