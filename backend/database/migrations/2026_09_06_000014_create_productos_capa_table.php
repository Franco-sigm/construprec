<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo de productos con que se puede materializar cada capa.
 *
 * Existe porque el tipo de capa no determina el producto: un revestimiento
 * interior puede ser yeso-carton o terciado ranurado, y cada uno se vende en
 * otra unidad y rinde distinto. Sin catalogo, el usuario tendria que saberse de
 * memoria cuanto rinde una caja de siding para poder cotizarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_capa', function (Blueprint $table) {
            $table->id();

            // A que capa sirve: arriostramiento, rev_interior, rev_exterior,
            // aislante o membrana. Es lo que filtra la lista que se le ofrece al
            // usuario en cada paso.
            $table->string('tipo', 30);

            $table->string('nombre', 150);
            $table->string('marca', 60)->nullable();

            // plancha | tabla | caja | rollo. Como lo despacha la barraca.
            $table->string('unidad_venta', 20);

            // Medidas de la pieza. De aca sale el rendimiento en los productos que
            // se venden por pieza, igual que en las escuadrias: el usuario reconoce
            // "1,22 x 2,44" y no "2,9768 m2", y con las medidas guardadas un
            // rendimiento mal tipeado deja de ser un error silencioso.
            $table->unsignedInteger('largo_mm')->nullable();
            $table->unsignedInteger('ancho_mm')->nullable();
            $table->decimal('espesor_mm', 7, 2)->nullable();

            // Cuanto ancho se pierde por solape con la pieza vecina.
            //
            // Es la diferencia entre lo que mide una tabla y lo que se ve de ella.
            // Una tabla de siding de 190 mm instalada con 30 de traslape solo deja
            // 160 a la vista: cotizarla por su ancho completo deja la obra corta en
            // casi un 16%. Cero en todo lo que se instala a tope.
            $table->unsignedSmallInteger('traslape_mm')->default(0);

            // Cuantas piezas trae la unidad de venta. Una caja de siding trae
            // varias tablas; una plancha es una sola.
            $table->unsignedSmallInteger('piezas_por_unidad')->default(1);

            // Rendimiento declarado por el fabricante, para lo que no tiene
            // geometria util: un rollo de lana o de membrana viene con sus metros
            // cuadrados en la etiqueta y no hay pieza que medir.
            $table->decimal('rendimiento_m2', 12, 4)->nullable();

            $table->boolean('fraccionable')->default(false);
            $table->decimal('merma_sugerida_pct', 5, 2)->default(0);

            $table->char('pais', 2)->default('CL');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['nombre', 'pais']);
            $table->index(['tipo', 'activo']);
        });

        Schema::table('proyecto_capas', function (Blueprint $table) {
            // De que producto del catalogo salio la capa. Anulable porque se puede
            // describir una capa a mano sin elegir de la lista. Si el producto se
            // borra, la capa sobrevive con su copia de nombre y rendimiento.
            $table->foreignId('producto_capa_id')->nullable()->after('material_id')
                ->constrained('productos_capa')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proyecto_capas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_capa_id');
        });

        Schema::dropIfExists('productos_capa');
    }
};
