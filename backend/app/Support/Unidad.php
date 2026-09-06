<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Unidades en que el usuario puede escribir una medida.
 *
 * La regla del producto es que toda entrada de medida ofrezca elegir unidad, y
 * que se pueda escribir una escuadria en pulgadas aunque el proyecto se haya
 * definido en metros. Por eso la unidad viaja pegada al valor y no es un ajuste
 * global del proyecto.
 */
enum Unidad: string
{
    case Milimetro = 'mm';
    case Centimetro = 'cm';
    case Metro = 'm';
    case Pulgada = 'in';
    case Pie = 'ft';

    /**
     * Cuantos milimetros vale una unidad de esta.
     *
     * El pie y la pulgada tienen equivalencia exacta y definida por acuerdo
     * internacional desde 1959 (1 pulgada = 25,4 mm), asi que no son
     * aproximaciones: convertir entre sistemas no pierde precision, solo la
     * pierde el redondeo posterior al milimetro entero.
     */
    public function enMilimetros(): float
    {
        return match ($this) {
            self::Milimetro => 1.0,
            self::Centimetro => 10.0,
            self::Metro => 1000.0,
            self::Pulgada => 25.4,
            self::Pie => 304.8,
        };
    }

    /**
     * Decimales con que tiene sentido mostrar un valor en esta unidad.
     *
     * Como todo se guarda en milimetros enteros, mostrar mas decimales de los
     * que la unidad puede representar solo expone el redondeo: 8 pies quedan
     * como 2438 mm y volver a pies da 7,99869..., que hay que mostrar como 8,00
     * y no como el numero completo, o el usuario cree que la app le cambio el
     * dato que escribio.
     */
    public function decimales(): int
    {
        return match ($this) {
            self::Milimetro => 0,
            self::Centimetro => 1,
            self::Metro => 3,
            self::Pie => 2,
            // Un decimal y no dos, por el redondeo al milimetro entero: 16" son
            // 406,4 mm, se guardan como 406 y vuelven como 15,98". Mostrar "15,98"
            // a quien escribio "16" se lee como un error de la app. Con un decimal
            // queda 16,0 y el 0,4 mm perdido es irrelevante en obra: sobre un muro
            // de 6 m acumula menos de 1 cm, muy por debajo de la tolerancia real
            // de un corte.
            self::Pulgada => 1,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Milimetro => 'mm',
            self::Centimetro => 'cm',
            self::Metro => 'm',
            self::Pulgada => '"',
            self::Pie => 'pies',
        };
    }

    /**
     * Acepta tanto el codigo ('m', 'ft') como los nombres que usa la gente.
     * La entrada viene de un formulario, no de un enum ya validado.
     */
    public static function desde(self|string $valor): self
    {
        if ($valor instanceof self) {
            return $valor;
        }

        $normalizado = mb_strtolower(trim($valor));

        return match ($normalizado) {
            'mm', 'milimetro', 'milimetros' => self::Milimetro,
            'cm', 'centimetro', 'centimetros' => self::Centimetro,
            'm', 'metro', 'metros' => self::Metro,
            'in', '"', 'pulgada', 'pulgadas' => self::Pulgada,
            'ft', "'", 'pie', 'pies' => self::Pie,
            default => throw new InvalidArgumentException("Unidad de medida desconocida: {$valor}"),
        };
    }
}
