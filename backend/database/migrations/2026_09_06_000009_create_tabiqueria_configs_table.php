<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabiqueria_configs', function (Blueprint $table) {
            $table->id();

            // Unica por proyecto: por ahora toda la tabiqueria comparte escuadria y
            // separacion. Cuando haya que diferenciar el muro exterior del divisorio,
            // basta reemplazar este unique por una referencia a la cara.
            $table->foreignId('proyecto_id')->unique()
                ->constrained('proyectos')->cascadeOnDelete();

            $table->foreignId('escuadria_id')->constrained('escuadrias')->restrictOnDelete();

            // Copia de la medida real al momento de configurar. Si manana se corrige
            // el catalogo de escuadrias, un proyecto ya calculado no puede cambiar
            // de dimensiones por debajo.
            $table->unsignedSmallInteger('escuadria_ancho_mm');
            $table->unsignedSmallInteger('escuadria_alto_mm');

            // Largo de pieza con que se compra. Define cuantas piezas salen de cada
            // tira y cuanto recorte queda: comprar 3,2 m para cortar de a 2,4 m
            // desperdicia casi un cuarto de cada tira.
            $table->unsignedInteger('largo_comercial_mm');

            // Distancia entre ejes de pies derechos: 400 o 600 mm si se piensa en
            // metros, 406 o 610 si se piensa en pulgadas (16" y 24"). Se guarda en mm
            // como todo lo demas, aunque se haya escrito en pulgadas.
            $table->unsignedSmallInteger('separacion_mm')->default(400);
            $table->string('separacion_unidad_ingreso', 4)->default('m');

            $table->boolean('solera_inferior')->default(true);

            // 1 o 2. La solera superior doble es lo habitual cuando el muro recibe
            // carga de techumbre o hay que amarrar tabiques perpendiculares, y
            // duplica los metros lineales de esa partida.
            $table->unsignedTinyInteger('soleras_superiores')->default(1);

            // El dintel sobre los vanos suele ser de escuadria mas alta que el pie
            // derecho, porque tiene que salvar la luz de la ventana sin flectar.
            // Nulo = usar la misma escuadria del tabique.
            $table->foreignId('escuadria_dintel_id')->nullable()
                ->constrained('escuadrias')->nullOnDelete();

            // Perdida por recortes en el despiece de piezas.
            $table->decimal('merma_pct', 5, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabiqueria_configs');
    }
};
