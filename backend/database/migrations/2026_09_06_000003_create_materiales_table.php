<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materiales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('nombre', 150);

            // Texto y no una tabla aparte: para agrupar "Aridos", "Pinturas" o
            // "Fierro" en el presupuesto alcanza, y ahorra un CRUD completo en
            // la primera version. Se normaliza si algun dia necesita jerarquia.
            $table->string('categoria', 60)->nullable();

            $table->text('descripcion')->nullable();

            // Como se compra: "plancha", "saco 25 kg", "tarro 4 L".
            $table->string('unidad_venta', 40);

            // Como se llega desde la superficie a la cantidad a comprar:
            //   dimensiones -> el rendimiento sale de largo x ancho (OSB, yeso-carton)
            //   superficie  -> el rendimiento viene dado en m2/unidad (pintura)
            //   volumen     -> superficie x espesor aplicado, luego / rendimiento (estuco)
            //   lineal      -> metros lineales (perfiles, molduras)
            //   unidad      -> cantidad directa, sin superficie (herrajes)
            // Texto y no ENUM: agregar un valor a un ENUM de MySQL exige un
            // ALTER TABLE que bloquea la tabla, y esta lista va a crecer.
            $table->string('tipo_calculo', 20)->default('superficie');

            // Medidas del producto, para los materiales de tipo `dimensiones`.
            // Se guardan las medidas y no el rendimiento por tres razones: el
            // usuario reconoce "plancha de 122 x 244" y no "rinde 2,9768 m2";
            // la multiplicacion la hace siempre el codigo, asi que un rendimiento
            // mal tipeado deja de ser un error silencioso; y con las medidas se
            // puede calcular despiece real en vez de dividir superficies.
            $table->unsignedInteger('largo_mm')->nullable();
            $table->unsignedInteger('ancho_mm')->nullable();

            // Grosor del PRODUCTO, no de la aplicacion en obra. Distingue el OSB
            // de 11 mm para muro del de 18 mm para piso: son materiales distintos,
            // con precios distintos. No confundir con espesor_aplicado_mm de la
            // linea, que es una decision de obra.
            $table->decimal('espesor_mm', 7, 2)->nullable();

            // Cuanto cubre UNA unidad de venta, medido en unidad_rendimiento.
            // En los materiales de tipo `dimensiones` se deriva de largo x ancho
            // al guardar; en el resto se carga a mano.
            $table->decimal('rendimiento', 12, 4)->nullable();

            // m2, m3 o ml.
            $table->string('unidad_rendimiento', 4)->nullable();

            // Perdida esperada por cortes, derrames y errores de obra. Este es el
            // valor sugerido del material; cada linea puede sobrescribirlo. Es una
            // aproximacion, no una verdad: la merma real depende de la geometria.
            $table->decimal('merma_pct', 5, 2)->default(0);

            // Distingue lo que se compra entero de lo que no. El cemento se vende
            // por saco y hay que redondear hacia arriba; la arena se vende por m3,
            // donde redondear 3,2 a 4 seria cotizar de mas sin razon.
            $table->boolean('fraccionable')->default(false);

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'nombre']);
            $table->index(['user_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materiales');
    }
};
