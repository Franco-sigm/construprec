<?php

use App\Support\Medida;
use App\Support\Unidad;

describe('conversion al guardar', function () {
    it('guarda metros como milimetros enteros', function () {
        expect(Medida::de(3.2, Unidad::Metro)->mm)->toBe(3200);
    });

    it('convierte pies con la equivalencia exacta de 304,8 mm', function () {
        expect(Medida::de(10, Unidad::Pie)->mm)->toBe(3048);
    });

    it('convierte pulgadas con la equivalencia exacta de 25,4 mm', function () {
        expect(Medida::de(4, Unidad::Pulgada)->mm)->toBe(102);
    });

    it('acepta la unidad escrita como la nombra la gente', function () {
        expect(Medida::de(1, 'pies')->mm)->toBe(305)
            ->and(Medida::de(1, 'pulgadas')->mm)->toBe(25)
            ->and(Medida::de(1, 'metros')->mm)->toBe(1000)
            ->and(Medida::de(1, '"')->mm)->toBe(25);
    });

    it('rechaza una unidad que no conoce', function () {
        Medida::de(1, 'varas');
    })->throws(InvalidArgumentException::class, 'Unidad de medida desconocida');

    it('rechaza medidas negativas', function () {
        Medida::de(-1, Unidad::Metro);
    })->throws(InvalidArgumentException::class, 'no puede ser negativa');
});

describe('mezclar unidades en un mismo proyecto', function () {
    // Es la regla central del producto: el proyecto puede estar definido en
    // metros y la escuadria escribirse en pulgadas, sin que el usuario tenga
    // que convertir nada a mano.
    it('deja escribir la escuadria en pulgadas aunque el muro este en metros', function () {
        $muro = Medida::de(6, Unidad::Metro);
        $separacion = Medida::de(16, Unidad::Pulgada);

        expect($muro->mm)->toBe(6000)
            ->and($separacion->mm)->toBe(406)
            ->and($muro->dividirPor($separacion))->toBeGreaterThan(14.7);
    });

    it('conserva la unidad en que se escribio cada dato', function () {
        expect(Medida::de(6, Unidad::Metro)->unidadIngreso)->toBe(Unidad::Metro)
            ->and(Medida::de(16, Unidad::Pulgada)->unidadIngreso)->toBe(Unidad::Pulgada);
    });
});

describe('devolver el dato como lo escribio el usuario', function () {
    // El redondeo al milimetro entero no puede notarse al mostrar el valor de
    // vuelta, o el usuario cree que la app le cambio lo que puso.
    it('devuelve 8 pies como 8, no como 7,99', function () {
        expect(Medida::de(8, Unidad::Pie)->mostrar())->toBe(8.0);
    });

    it('devuelve 16 pulgadas como 16, no como 15,98', function () {
        expect(Medida::de(16, Unidad::Pulgada)->mostrar())->toBe(16.0);
    });

    it('devuelve 2,44 metros intactos', function () {
        expect(Medida::de(2.44, Unidad::Metro)->mostrar())->toBe(2.44);
    });

    it('muestra la misma medida en otra unidad', function () {
        expect(Medida::de(3.2, Unidad::Metro)->mostrarEn(Unidad::Pie))->toBe(10.5);
    });
});

describe('aritmetica', function () {
    it('suma y multiplica conservando la unidad de ingreso', function () {
        $a = Medida::de(2, Unidad::Metro);

        expect($a->mas(Medida::de(500, Unidad::Milimetro))->mm)->toBe(2500)
            ->and($a->por(3)->mm)->toBe(6000)
            ->and($a->por(3)->unidadIngreso)->toBe(Unidad::Metro);
    });

    it('acota la resta en cero para no propagar superficies negativas', function () {
        // Pasa cuando los datos vienen mal: una ventana mas ancha que su cara.
        $cara = Medida::de(2, Unidad::Metro);
        $ventana = Medida::de(3, Unidad::Metro);

        expect($cara->menos($ventana)->mm)->toBe(0);
    });

    it('calcula superficie en metros cuadrados', function () {
        $largo = Medida::de(6, Unidad::Metro);
        $alto = Medida::de(2.4, Unidad::Metro);

        expect($largo->porM2($alto))->toBe(14.4);
    });

    it('no divide por cero', function () {
        Medida::de(1, Unidad::Metro)->dividirPor(Medida::cero());
    })->throws(InvalidArgumentException::class, 'dividir una medida por cero');
});

describe('escuadrias reales del catalogo', function () {
    it('convierte las medidas nominales de un 2x3 a milimetros', function () {
        // La nominal es teorica: el 2x3 seco cepillado real mide 41 x 65 mm.
        // Este test fija la conversion, no la medida real de la pieza.
        expect(Medida::de(2, Unidad::Pulgada)->mm)->toBe(51)
            ->and(Medida::de(3, Unidad::Pulgada)->mm)->toBe(76);
    });

    it('convierte el largo comercial de 3,2 m', function () {
        expect(Medida::de(3.2, Unidad::Metro)->mm)->toBe(3200)
            ->and(Medida::de(3200, Unidad::Milimetro)->metros())->toBe(3.2);
    });
});
