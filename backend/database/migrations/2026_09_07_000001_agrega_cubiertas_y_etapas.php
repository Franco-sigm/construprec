<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que la techumbre necesita del esquema que ya existía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos_capa', function (Blueprint $table) {
            // Cada cubierta soporta un vano distinto entre apoyos: el zinc pide
            // costaneras cada 80-120 cm y una teja de arcilla, que pesa mucho más,
            // bastante menos. Es un dato del producto y no del proyecto, así que
            // viene con él y el asistente lo propone al elegirlo.
            $table->unsignedSmallInteger('separacion_costaneras_mm')->nullable()->after('traslape_mm');

            // Algunas cubiertas necesitan tablero continuo debajo y no sólo
            // costaneras: la teja asfáltica se clava sobre OSB o terciado. Que el
            // producto lo declare evita que se cotice un techo que no se puede
            // armar.
            $table->boolean('requiere_tablero')->default(false)->after('separacion_costaneras_mm');
        });

        Schema::table('presupuesto_lineas', function (Blueprint $table) {
            // De qué etapa de la obra viene la línea. Sin esto el presupuesto es
            // una lista plana y no se puede saber cuánto cuesta el techo aparte
            // de los muros, que es justo lo que se compara al decidir.
            $table->string('etapa', 20)->default('muros')->after('origen');
            $table->index(['presupuesto_id', 'etapa']);
        });
    }

    public function down(): void
    {
        Schema::table('productos_capa', function (Blueprint $table) {
            $table->dropColumn(['separacion_costaneras_mm', 'requiere_tablero']);
        });

        Schema::table('presupuesto_lineas', function (Blueprint $table) {
            $table->dropIndex(['presupuesto_id', 'etapa']);
            $table->dropColumn('etapa');
        });
    }
};
