<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escuadrias', function (Blueprint $table) {
            $table->id();

            // El nombre comercial en pulgadas ("2x4") es como se pide y se cotiza
            // la madera en todo el rubro, aunque no corresponda a la medida real
            // de la pieza. Es lo unico que el usuario reconoce, asi que es lo que
            // se le muestra.
            $table->string('nominal', 20);

            // Medida efectiva de la pieza cepillada. Un 2x4 no mide 50,8 x 101,6 mm
            // sino cerca de 41 x 91: el cepillado se come la diferencia. El despiece
            // y el ancho real que ocupa un pie derecho dependen de esta medida, no
            // de la nominal, y confundirlas descuadra el conteo en muros largos.
            // En el orden en que se nombra: para un "2x3", ancho es la de 2" y alto
            // la de 3". En un tabique la segunda es la profundidad de la cavidad, y
            // por lo tanto el espesor maximo de aislante que cabe adentro.
            $table->unsignedSmallInteger('ancho_real_mm');
            $table->unsignedSmallInteger('alto_real_mm');

            // verde | seco | seco_cepillado, los tres estados que distingue la NCh2824
            // y con los que se vende en barraca. No es una etiqueta: cada estado
            // tiene medida real y precio distintos, porque la pieza encoge al secarse
            // y el cepillado le come milimetros por las cuatro caras. Sin esta
            // columna "2x3" seria un producto ambiguo y el despiece usaria la medida
            // equivocada.
            //
            // Ademas importa por una razon que no es dimensional: la madera verde se
            // tuerce al secarse ya montada dentro del tabique. Que el estado sea un
            // dato explicito permite advertirlo en vez de dejar que se elija a ciegas
            // por precio.
            $table->string('estado', 20)->default('seco_cepillado');

            // Largos en que se vende la pieza, en mm. JSON y no una tabla hija
            // porque la lista se lee siempre entera, para elegir el largo que deje
            // menos recorte; nunca se consulta un largo suelto. Una tabla aparte
            // solo agregaria un join y un CRUD que nadie va a usar.
            $table->json('largos_comerciales_mm');

            // Las medidas reales cambian entre paises: el 2x4 estadounidense es
            // 38 x 89 mm y el chileno ronda 41 x 91. Sin esta columna, un catalogo
            // global daria despieces equivocados fuera de Chile.
            $table->char('pais', 2)->default('CL');

            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['nominal', 'estado', 'pais']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escuadrias');
    }
};
