<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cara_vanos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cara_id')->constrained('proyecto_caras')->cascadeOnDelete();

            // puerta | ventana. Cambia el despiece, no solo la etiqueta: una puerta
            // interrumpe la solera inferior y no lleva piezas bajo el vano; una
            // ventana conserva la solera y si lleva pies derechos cortos abajo.
            $table->string('tipo', 20);

            $table->unsignedInteger('ancho_mm');
            $table->unsignedInteger('alto_mm');

            // Altura del piso al borde inferior del vano. En una puerta es 0. En una
            // ventana define el largo de las piezas cortas bajo el antepecho, que de
            // otro modo se contarian como pies derechos enteros y sobrestimarian la
            // madera.
            $table->unsignedInteger('antepecho_mm')->default(0);

            // Varias ventanas identicas en la misma cara caben en una fila. Evita
            // que el usuario repita el mismo formulario tres veces seguidas.
            $table->unsignedSmallInteger('cantidad')->default(1);

            $table->string('unidad_ingreso', 4)->default('m');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cara_vanos');
    }
};
