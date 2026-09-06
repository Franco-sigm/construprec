<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // De que proyecto salio este presupuesto. Anulable porque tambien se
            // puede armar uno a mano, sin pasar por el asistente de geometria. Si
            // el proyecto se borra el presupuesto sobrevive: es un documento ya
            // entregado, y perderlo por limpiar proyectos seria inaceptable.
            $table->foreignId('proyecto_id')->nullable()
                ->constrained('proyectos')->nullOnDelete();

            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();

            // Moneda en la que se expresa el total. Cada linea puede venir de un
            // precio cargado en otra moneda; la conversion se resuelve al crear
            // la linea y se congela ahi.
            $table->char('moneda_destino', 3);

            $table->date('fecha');

            // borrador | emitido. Un presupuesto emitido no se recalcula nunca:
            // es un documento historico, no una consulta viva.
            $table->string('estado', 20)->default('borrador');

            // Total ya convertido a moneda_destino. Se guarda en vez de sumarse
            // al vuelo para que el documento no cambie si despues suben los
            // precios de los materiales o se mueve la tasa de cambio.
            $table->decimal('total', 16, 4)->default(0);

            $table->timestamps();

            $table->foreign('moneda_destino')->references('codigo')->on('monedas')->restrictOnDelete();
            $table->index(['user_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuestos');
    }
};
