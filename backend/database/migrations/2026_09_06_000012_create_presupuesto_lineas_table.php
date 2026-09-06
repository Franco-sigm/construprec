<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuesto_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')->constrained('presupuestos')->cascadeOnDelete();

            // Anulable a proposito. Si el usuario borra un material, la linea
            // sobrevive intacta con su fotografia: el presupuesto que ya cotizo no
            // puede cambiar ni romperse. Este campo solo sirve para trazar de donde
            // salio la linea, nunca como fuente del precio.
            $table->foreignId('material_id')->nullable()
                ->constrained('materiales')->nullOnDelete();

            $table->unsignedInteger('orden')->default(0);

            // estructura | capa | manual. Que parte del calculo produjo la linea.
            // Un pie derecho sale del despiece del tabique, una plancha de OSB sale
            // de una capa, y una linea manual la agrego el usuario a mano. Sin este
            // campo no se puede reagrupar el presupuesto por partida ni saber que
            // se recalcula al cambiar la geometria.
            $table->string('origen', 20)->default('capa');

            // --- de donde sale la cantidad ---
            // La magnitud de partida, antes de aplicar rendimiento y merma. No es
            // siempre una superficie: un pie derecho se cuenta en piezas y una
            // solera en metros lineales. Llamarla `superficie` obligaria a inventar
            // metros cuadrados donde no los hay.
            $table->decimal('magnitud', 12, 4);

            // m2, m3, ml o un.
            $table->string('unidad_magnitud', 4);

            $table->decimal('merma_pct', 5, 2)->default(0);

            // --- fotografia del material al momento de cotizar ---
            // Todo lo que sigue se copia del material y del precio vigente. Sin esta
            // copia, subir el precio del cemento cambiaria todos los presupuestos ya
            // emitidos, incluido el que el cliente tiene en mano.
            $table->string('nombre_material', 150);
            $table->string('categoria_material', 60)->nullable();
            $table->string('unidad_venta', 40);
            $table->decimal('rendimiento', 12, 4);
            $table->decimal('precio_unitario', 16, 4);
            $table->char('moneda_origen', 3);

            // Tasa ya compuesta origen -> destino. Se guarda resuelta para que el
            // subtotal sea reproducible sin volver a consultar tasas_cambio ni
            // repetir el paso por el dolar.
            $table->decimal('tasa_cambio', 18, 8)->default(1);

            // --- resultado del calculo ---
            $table->decimal('cantidad', 12, 4);          // antes de redondear
            $table->decimal('cantidad_comprar', 12, 4);  // lo que se compra
            $table->decimal('subtotal', 16, 4);          // ya en moneda destino

            // Desglose que explica la cantidad: de que caras vino, cuantas piezas de
            // cada largo, cuanta superficie se descontó por vanos. Es lo que permite
            // que el usuario audite un numero que no le cuadra en vez de tener que
            // confiar. JSON porque la forma del desglose cambia segun el origen y no
            // se consulta por sus campos, solo se muestra.
            $table->json('detalle')->nullable();

            $table->timestamps();

            $table->foreign('moneda_origen')->references('codigo')->on('monedas')->restrictOnDelete();
            $table->index(['presupuesto_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_lineas');
    }
};
