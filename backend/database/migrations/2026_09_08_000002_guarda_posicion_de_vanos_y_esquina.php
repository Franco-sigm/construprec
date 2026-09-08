<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos datos que el asistente dejaba elegir y el guardado tiraba a la basura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cara_vanos', function (Blueprint $table) {
            // Desde que pie derecho arranca el marco del vano.
            //
            // Se elegia con las flechas y se perdia al guardar: al reabrir el
            // proyecto los vanos volvian repartidos automaticamente, y el trabajo
            // de ubicarlos uno por uno habia que rehacerlo. Nulo sigue
            // significando "reparteme tu", que es el estado inicial.
            $table->unsignedSmallInteger('desde_tramo')->nullable()->after('cantidad');
        });

        Schema::table('tabiqueria_configs', function (Blueprint $table) {
            // Piezas en el encuentro de dos muros, contando las dos que ya aporta
            // cada cara. Al reabrir volvia siempre a tres, asi que quien elegia
            // cuatro perdia la eleccion sin que nada lo avisara.
            $table->unsignedTinyInteger('piezas_por_esquina')->default(3)->after('filas_cadenetas');
        });
    }

    public function down(): void
    {
        Schema::table('cara_vanos', fn (Blueprint $t) => $t->dropColumn('desde_tramo'));
        Schema::table('tabiqueria_configs', fn (Blueprint $t) => $t->dropColumn('piezas_por_esquina'));
    }
};
