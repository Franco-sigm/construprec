<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();

            // Por ahora solo 'madera'. Texto y no ENUM: agregar un valor a un ENUM
            // de MySQL exige un ALTER TABLE que bloquea la tabla, y aca van a
            // entrar metalcon y albanileria.
            $table->string('sistema', 20)->default('madera');

            // Que parte de la obra se esta calculando. Hoy solo 'muros'; la
            // techumbre es otro despiece completo y entra despues.
            $table->string('alcance', 20)->default('muros');

            // Medidas de la planta rectangular. Se guardan para poder regenerar las
            // cuatro caras si el usuario corrige el rectangulo, pero el calculo NO
            // las lee: lee proyecto_caras, donde cada cara ya pudo editarse aparte.
            $table->unsignedInteger('ancho_mm')->nullable();
            $table->unsignedInteger('largo_mm')->nullable();
            $table->unsignedInteger('alto_mm')->nullable();

            // Toda medida se guarda en milimetros enteros, sin importar en que
            // unidad se escribio. Asi el calculo nunca convierte y no arrastra
            // error de redondeo entre pasos. Esta columna solo recuerda como
            // mostrarle el valor de vuelta al usuario: es presentacion, no dato de
            // calculo. Va una por fila y no una por columna porque nadie escribe
            // el ancho en metros y el largo en pies en el mismo formulario.
            $table->string('unidad_ingreso', 4)->default('m');

            $table->string('estado', 20)->default('borrador');
            $table->timestamps();

            $table->index(['user_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
