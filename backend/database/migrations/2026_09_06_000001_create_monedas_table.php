<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monedas', function (Blueprint $table) {
            // El codigo ISO 4217 es la llave primaria: es estable, universal, y
            // evita un join solo para mostrar "CLP" en cada linea del presupuesto.
            $table->char('codigo', 3)->primary();
            $table->string('nombre', 60);
            $table->string('simbolo', 8);

            // Decimales que admite la moneda. CLP y PYG usan 0: un monto como
            // "$1.500,50" en pesos chilenos no existe, y redondear con dos
            // decimales fijos produciria totales imposibles de pagar.
            $table->unsignedTinyInteger('decimales')->default(2);

            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monedas');
    }
};
