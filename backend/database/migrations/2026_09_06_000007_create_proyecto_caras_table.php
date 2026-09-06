<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_caras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();

            $table->unsignedTinyInteger('orden');

            // "Cara 1", "Norte", "Muro living". El usuario necesita distinguirlas
            // para saber en cual esta poniendo cada ventana.
            $table->string('nombre', 60);

            $table->unsignedInteger('largo_mm');
            $table->unsignedInteger('alto_mm');
            $table->string('unidad_ingreso', 4)->default('m');

            // Decide que capas aplican sobre esta cara. La membrana hidrofuga y el
            // revestimiento exterior solo tienen sentido contra el exterior; un
            // tabique divisorio lleva revestimiento interior por sus dos lados y
            // ninguno de los dos.
            $table->boolean('es_exterior')->default(true);

            $table->timestamps();

            // La app crea las cuatro caras a partir de ancho/largo/alto, pero quedan
            // editables una por una: es lo que va a permitir plantas en L o muros a
            // distinta altura sin tocar el esquema.
            $table->unique(['proyecto_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_caras');
    }
};
