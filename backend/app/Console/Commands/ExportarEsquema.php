<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vuelca el esquema y los datos de referencia a un archivo .sql.
 *
 * Sirve para el caso en que el hosting no da acceso por consola y sólo queda
 * phpMyAdmin. Si hay consola, `php artisan migrate --force` es mejor: las
 * migraciones son la fuente de verdad del esquema y este archivo es una copia
 * que puede quedar vieja.
 *
 * Por eso es un comando y no un archivo versionado: se regenera en el momento y
 * no hay forma de que se desincronice sin que nadie lo note.
 */
class ExportarEsquema extends Command
{
    protected $signature = 'construprec:esquema-sql {--salida=docs/esquema.sql}';

    protected $description = 'Genera el SQL de creación de tablas y datos de referencia, para importar por phpMyAdmin';

    /**
     * Tablas cuyos datos también se exportan.
     *
     * Son el catálogo —monedas, escuadrías, productos— y el registro de
     * migraciones. Ese último es fundamental: si se importa el SQL sin él,
     * Laravel cree que no se ha migrado nada y un `migrate` posterior intenta
     * crear tablas que ya existen.
     */
    private const CON_DATOS = ['migrations', 'monedas', 'escuadrias', 'productos_capa'];

    public function handle(): int
    {
        $salida = base_path($this->option('salida'));
        @mkdir(dirname($salida), 0755, true);

        // Filtrado por la base actual: en un servidor MySQL compartido, el
        // listado trae las tablas de TODAS las bases a las que llega el usuario, y
        // sin filtrar se intentaría exportar la tabla de otro proyecto.
        $base = DB::getDatabaseName();

        $tablas = collect(Schema::getTables())
            ->where('schema', $base)
            ->pluck('name')
            ->sort()
            ->values();

        $sql = $this->encabezado($tablas->count());

        // Las llaves foráneas se apagan mientras dura la importación: las tablas
        // se crean en orden alfabético y una puede referenciar a otra que todavía
        // no existe.
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\nSET NAMES utf8mb4;\n\n";

        foreach ($tablas as $tabla) {
            $crear = (array) DB::selectOne("SHOW CREATE TABLE `{$tabla}`");
            $sql .= "DROP TABLE IF EXISTS `{$tabla}`;\n".end($crear).";\n\n";
        }

        foreach (self::CON_DATOS as $tabla) {
            if (! $tablas->contains($tabla)) {
                continue;
            }

            $sql .= $this->datos($tabla);
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        file_put_contents($salida, $sql);

        $this->info(sprintf(
            '%s · %d tablas · %s',
            $this->option('salida'),
            $tablas->count(),
            $this->tamano(strlen($sql)),
        ));

        $this->line('  Con datos: '.implode(', ', self::CON_DATOS));
        $this->line('  Sin datos: usuarios, proyectos y presupuestos quedan vacíos a propósito.');

        return self::SUCCESS;
    }

    private function datos(string $tabla): string
    {
        $filas = DB::table($tabla)->get();

        if ($filas->isEmpty()) {
            return '';
        }

        $columnas = collect(array_keys((array) $filas->first()))
            ->map(fn (string $c) => "`{$c}`")
            ->implode(', ');

        $valores = $filas->map(function ($fila) {
            $celdas = collect((array) $fila)->map(function ($v) {
                if ($v === null) {
                    return 'NULL';
                }

                if (is_bool($v)) {
                    return $v ? '1' : '0';
                }

                if (is_int($v) || is_float($v)) {
                    return (string) $v;
                }

                // addslashes no basta: un valor con comillas o barras invertidas
                // rompería el INSERT y, peor, podría inyectar SQL si el dato
                // viniera de afuera.
                return "'".str_replace(
                    ['\\', "'", "\n", "\r", "\0", "\x1a"],
                    ['\\\\', "\\'", '\\n', '\\r', '\\0', '\\Z'],
                    (string) $v,
                )."'";
            })->implode(', ');

            return "  ({$celdas})";
        })->implode(",\n");

        return "-- Datos de {$tabla}\nINSERT INTO `{$tabla}` ({$columnas}) VALUES\n{$valores};\n\n";
    }

    private function encabezado(int $tablas): string
    {
        return <<<SQL
        -- Construprec — esquema completo y datos de referencia
        --
        -- GENERADO el {$this->ahora()} con `php artisan construprec:esquema-sql`.
        -- No editar a mano: la fuente de verdad son las migraciones, y este archivo
        -- es una copia para importar donde no haya acceso por consola.
        --
        -- Cómo se usa: en phpMyAdmin, elegir la base y usar Importar.
        -- Borra y recrea las {$tablas} tablas: se pierde lo que hubiera dentro.
        --
        -- Incluye el registro de migraciones ya aplicadas. Sin él, Laravel creería
        -- que no se ha migrado nada e intentaría crear tablas que ya existen.


        SQL;
    }

    private function ahora(): string
    {
        return now()->format('d/m/Y H:i');
    }

    private function tamano(int $bytes): string
    {
        return $bytes > 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
