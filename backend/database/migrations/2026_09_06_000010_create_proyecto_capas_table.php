<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_capas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();

            // arriostramiento | rev_interior | rev_exterior | aislante | membrana.
            // El tipo decide sobre que superficie se calcula la capa, no que producto
            // se usa: dos proyectos pueden arriostrar con OSB y con terciado y el
            // calculo es el mismo.
            $table->string('tipo', 30);

            // exterior | interior | ambas. Un aislante va una vez por muro, porque
            // ocupa el interior del tabique; un revestimiento interior en un tabique
            // divisorio va dos veces, una por cada lado. Sin esta distincion el
            // divisorio quedaria cotizado a la mitad.
            $table->string('aplicacion', 10)->default('exterior');

            // El material del catalogo, cuando el usuario eligio uno. Sirve para
            // proponerle el ultimo precio que pago y para heredar el rendimiento.
            // Anulable porque se puede cotizar una capa sin dar de alta el producto.
            $table->foreignId('material_id')->nullable()
                ->constrained('materiales')->nullOnDelete();

            // Cuando no hay material en el catalogo, la capa se describe aca misma.
            // Tambien queda como copia de lo que se eligio: si despues borran el
            // material, la capa sigue diciendo que era.
            $table->string('nombre', 150);
            $table->string('unidad_venta', 40);
            $table->decimal('rendimiento', 12, 4);
            $table->string('unidad_rendimiento', 4);

            // Distingue lo que se compra entero de lo que no. Una plancha de OSB se
            // redondea hacia arriba; un rollo de lana que se corta a medida no
            // necesariamente.
            $table->boolean('fraccionable')->default(false);

            $table->decimal('merma_pct', 5, 2)->default(0);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();

            // Sin unique sobre (proyecto_id, tipo): un mismo tipo puede repetirse,
            // por ejemplo una barrera bajo el siding mas el siding mismo. El orden
            // es el que manda en como se listan.
            $table->index(['proyecto_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_capas');
    }
};
