<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precios_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materiales')->cascadeOnDelete();

            // Doce digitos enteros y cuatro decimales: soporta monedas de valor
            // bajo frente al dolar, donde un presupuesto grande llega a cientos
            // de millones de unidades.
            $table->decimal('precio', 16, 4);
            $table->char('moneda', 3);

            // Historial en vez de una columna en `materiales`: en un rubro con
            // inflacion, "cuanto costaba esto en marzo" es una pregunta habitual,
            // y sin historial la respuesta se pierde en cada actualizacion.
            $table->date('vigente_desde');

            $table->string('proveedor', 100)->nullable();
            $table->timestamps();

            $table->foreign('moneda')->references('codigo')->on('monedas')->restrictOnDelete();
            $table->index(['material_id', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_material');
    }
};
