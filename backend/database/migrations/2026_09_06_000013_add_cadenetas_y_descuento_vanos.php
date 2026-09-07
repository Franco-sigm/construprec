<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos decisiones que aparecieron al escribir el calculo y que el esquema
 * original no contemplaba. Van en una migracion aparte y no editando las
 * anteriores porque esas ya estan publicadas: cambiarlas dejaria a cualquier
 * base que ya las corrio sin manera de llegar a este estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabiqueria_configs', function (Blueprint $table) {
            // Cuantas filas de cadenetas lleva el tabique. Cero las desactiva.
            // No cuestan tiras: al ser la pieza mas corta, salen del recorte que
            // dejan los cortes largos.
            $table->unsignedTinyInteger('filas_cadenetas')->default(1)->after('soleras_superiores');
        });

        Schema::table('proyecto_capas', function (Blueprint $table) {
            // Si la capa descuenta la superficie de puertas y ventanas.
            //
            // Nulo significa "lo que corresponda al tipo", que es lo correcto casi
            // siempre: la membrana hidrofuga no descuenta porque se corre entera
            // sobre la fachada y se recorta despues, y el aislante si porque va
            // dentro de la cavidad, donde el vano no existe. La columna existe para
            // poder contradecir ese criterio en un proyecto puntual sin tocar
            // codigo, no para tener que llenarla siempre.
            $table->boolean('descuenta_vanos')->nullable()->after('fraccionable');
        });
    }

    public function down(): void
    {
        Schema::table('tabiqueria_configs', function (Blueprint $table) {
            $table->dropColumn('filas_cadenetas');
        });

        Schema::table('proyecto_capas', function (Blueprint $table) {
            $table->dropColumn('descuenta_vanos');
        });
    }
};
