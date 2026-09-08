<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La techumbre de un proyecto guardado.
 *
 * Va en su propia tabla y no en columnas de `proyectos` porque es una etapa
 * opcional: un proyecto puede quedarse en los muros, y en ese caso no existe
 * fila. Ademas ya son quince campos, que en la tabla del proyecto quedarian
 * mezclados con los de la planta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('techumbre_configs', function (Blueprint $table) {
            $table->id();

            // Unica por proyecto: por ahora una techumbre por obra.
            $table->foreignId('proyecto_id')->unique()
                ->constrained('proyectos')->cascadeOnDelete();

            // 1 o 2. Mas aguas es otra geometria, no un parametro mayor.
            $table->unsignedTinyInteger('aguas')->default(2);

            $table->unsignedInteger('luz_mm');
            $table->unsignedInteger('largo_mm');

            // Cuanto sube el caballete sobre el apoyo. La pendiente se deriva de
            // aca y no se guarda: guardar las dos permitiria que se contradigan.
            $table->unsignedInteger('altura_cumbrera_mm');

            // Vuelo del techo mas alla del muro, en proyeccion horizontal.
            $table->unsignedInteger('alero_mm')->default(0);

            $table->string('unidad_ingreso', 4)->default('m');

            $table->foreignId('escuadria_id')->constrained('escuadrias')->restrictOnDelete();

            // Copia de la medida real al configurar, igual que en la tabiqueria: si
            // manana se corrige el catalogo, un proyecto ya calculado no puede
            // cambiar de seccion por debajo.
            $table->unsignedSmallInteger('escuadria_ancho_mm');
            $table->unsignedSmallInteger('escuadria_alto_mm');

            // Nula = se usa la misma escuadria de la cercha, que es mas gruesa de
            // lo necesario pero nunca deja el techo corto.
            $table->foreignId('escuadria_costanera_id')->nullable()
                ->constrained('escuadrias')->nullOnDelete();

            $table->unsignedInteger('largo_comercial_mm');
            $table->unsignedSmallInteger('separacion_cerchas_mm');
            $table->unsignedSmallInteger('separacion_costaneras_mm');
            $table->string('separacion_unidad_ingreso', 4)->default('m');

            $table->decimal('merma_pct', 5, 2)->default(0);

            $table->timestamps();
        });

        Schema::table('proyecto_capas', function (Blueprint $table) {
            // De que etapa es la capa. La cubierta va sobre la techumbre y se
            // calcula sobre la superficie de faldon, que es mayor que la planta:
            // mezclarla con las del muro la cotizaria sobre los metros equivocados.
            $table->string('etapa', 20)->default('muros')->after('proyecto_id');
            $table->index(['proyecto_id', 'etapa']);
        });
    }

    public function down(): void
    {
        Schema::table('proyecto_capas', function (Blueprint $table) {
            $table->dropIndex(['proyecto_id', 'etapa']);
            $table->dropColumn('etapa');
        });

        Schema::dropIfExists('techumbre_configs');
    }
};
