<?php

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Una medida lineal, guardada siempre en milimetros enteros.
 *
 * El milimetro entero es la unica representacion interna a proposito: si cada
 * paso del calculo convirtiera entre metros, pies y pulgadas, el error de
 * redondeo se acumularia a lo largo de un muro y el despiece dejaria de cuadrar.
 * Convertir una vez al entrar y una vez al mostrar acota el error a esos dos
 * puntos.
 *
 * `unidadIngreso` no participa de ningun calculo: solo recuerda como escribio el
 * dato el usuario, para devolverselo en la misma unidad y que no sienta que la
 * app le cambio lo que puso.
 */
final readonly class Medida implements Stringable
{
    private function __construct(
        public int $mm,
        public Unidad $unidadIngreso,
    ) {}

    /**
     * Construye desde lo que escribio el usuario.
     *
     * El redondeo al milimetro es deliberado: en obra nadie corta con precision
     * sub-milimetrica, y un entero evita que se arrastren decimales binarios que
     * despues aparecen como diferencias inexplicables en el total.
     */
    public static function de(int|float $valor, Unidad|string $unidad): self
    {
        $unidad = Unidad::desde($unidad);

        if (! is_finite((float) $valor)) {
            throw new InvalidArgumentException('La medida debe ser un numero finito.');
        }

        if ($valor < 0) {
            throw new InvalidArgumentException('Una medida no puede ser negativa.');
        }

        return new self((int) round($valor * $unidad->enMilimetros()), $unidad);
    }

    /**
     * Reconstruye desde lo que hay guardado: los mm de la columna y la unidad en
     * que se habia escrito.
     */
    public static function desdeMm(int $mm, Unidad|string $unidadIngreso = Unidad::Milimetro): self
    {
        if ($mm < 0) {
            throw new InvalidArgumentException('Una medida no puede ser negativa.');
        }

        return new self($mm, Unidad::desde($unidadIngreso));
    }

    public static function cero(Unidad|string $unidad = Unidad::Milimetro): self
    {
        return new self(0, Unidad::desde($unidad));
    }

    /** Valor exacto en la unidad pedida, sin redondear. Para calcular, no para mostrar. */
    public function en(Unidad|string $unidad): float
    {
        return $this->mm / Unidad::desde($unidad)->enMilimetros();
    }

    /** Valor redondeado a los decimales que la unidad puede representar. Para mostrar. */
    public function mostrarEn(Unidad|string $unidad): float
    {
        $unidad = Unidad::desde($unidad);

        return round($this->en($unidad), $unidad->decimales());
    }

    /** En la misma unidad en que se escribio. */
    public function mostrar(): float
    {
        return $this->mostrarEn($this->unidadIngreso);
    }

    public function metros(): float
    {
        return $this->en(Unidad::Metro);
    }

    // El resultado conserva la unidad de ingreso del operando izquierdo: si el
    // usuario venia trabajando en pies, lo derivado se le muestra en pies.

    public function mas(self $otra): self
    {
        return new self($this->mm + $otra->mm, $this->unidadIngreso);
    }

    /**
     * Resta acotada en cero. Descontar vanos de un muro puede dar negativo cuando
     * los datos vienen mal (una ventana mas ancha que su cara); devolver cero deja
     * que la validacion reporte el problema en vez de propagar una superficie
     * negativa que terminaria restando material del presupuesto.
     */
    public function menos(self $otra): self
    {
        return new self(max(0, $this->mm - $otra->mm), $this->unidadIngreso);
    }

    public function por(int|float $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('Una medida no puede multiplicarse por un factor negativo.');
        }

        return new self((int) round($this->mm * $factor), $this->unidadIngreso);
    }

    /** Cuantas veces cabe otra medida en esta. Sirve para separaciones y despiece. */
    public function dividirPor(self $otra): float
    {
        if ($otra->mm === 0) {
            throw new InvalidArgumentException('No se puede dividir una medida por cero.');
        }

        return $this->mm / $otra->mm;
    }

    /**
     * Superficie en metros cuadrados. Se expone en m2 y no en mm2 porque es la
     * unidad en que se cotiza.
     *
     * Redondea a cuatro decimales, que es la precision de la columna decimal(12,4)
     * que va a recibir el valor. Sin eso, 6 x 2,4 da 14.399999999999999 en coma
     * flotante: se guardaria igual como 14,4000, pero cualquier comparacion contra
     * el valor calculado fallaria por una diferencia que no existe en la base.
     */
    public function porM2(self $otra): float
    {
        return round($this->metros() * $otra->metros(), 4);
    }

    public function esCero(): bool
    {
        return $this->mm === 0;
    }

    public function mayorQue(self $otra): bool
    {
        return $this->mm > $otra->mm;
    }

    /** Devuelve la misma medida pero recordada en otra unidad. No cambia el valor. */
    public function comoIngresadaEn(Unidad|string $unidad): self
    {
        return new self($this->mm, Unidad::desde($unidad));
    }

    public function __toString(): string
    {
        return $this->mostrar().' '.$this->unidadIngreso->etiqueta();
    }
}
