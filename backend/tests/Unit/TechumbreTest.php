<?php

use App\Services\Madera\ParametrosCorte;
use App\Services\Madera\PlanCorteService;
use App\Services\Madera\RolPieza;
use App\Services\Techumbre\ConfiguracionTechumbre;
use App\Services\Techumbre\DespieceTechumbreService;
use App\Support\Medida;
use App\Support\Unidad;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Techo a dos aguas sobre la planta de 6 x 4: luz 4 m, largo 6 m, cumbrera +1 m. */
function techo(array $sobrescribir = []): ConfiguracionTechumbre
{
    $m = fn (float $v) => Medida::de($v, Unidad::Metro);

    return new ConfiguracionTechumbre(...array_merge([
        'aguas' => 2,
        'luz' => $m(4),
        'largo' => $m(6),
        'alturaCumbrera' => $m(1),
        'escuadriaAncho' => Medida::desdeMm(41),
        'escuadriaAlto' => Medida::desdeMm(138),
        'separacionCerchas' => $m(1),
        'separacionCostaneras' => $m(0.8),
        'costaneraAncho' => Medida::desdeMm(41),
        'costaneraAlto' => Medida::desdeMm(65),
        'alero' => $m(0.5),
        'corte' => new ParametrosCorte(Medida::de(4, Unidad::Metro), 5.0),
    ], $sobrescribir));
}

describe('la pendiente sale de la altura de cumbrera', function () {
    it('reparte la luz entre los dos faldones', function () {
        // Con 4 m de luz a dos aguas, cada par avanza 2 m en horizontal.
        expect(techo()->avanceHorizontal()->mm)->toBe(2000);
    });

    it('un faldón único cubre la luz completa', function () {
        expect(techo(['aguas' => 1])->avanceHorizontal()->mm)->toBe(4000);
    });

    it('convierte la altura en pendiente y en grados', function () {
        // 1 m de subida en 2 m de avance: 50 % y 26,57°.
        expect(techo()->pendientePorcentaje())->toBe(50.0)
            ->and(techo()->pendienteGrados())->toBe(26.57);
    });

    it('la misma altura da la mitad de pendiente en una agua', function () {
        // El avance se duplica, así que el techo queda más tendido.
        expect(techo(['aguas' => 1])->pendientePorcentaje())->toBe(25.0);
    });
});

describe('largos de corte', function () {
    it('estira el par por la pendiente y le suma el alero', function () {
        // (2,00 + 0,50) x raíz(1 + 0,5²) = 2,5 x 1,118 = 2,795 m.
        expect(techo()->largoPar()->mm)->toBe(2795);
    });

    it('la diagonal es la mitad del par sin alero', function () {
        // Va del pie del pendolón a la mitad del par: por semejanza de
        // triángulos, exactamente la mitad. 2,236 / 2 = 1,118.
        expect(techo()->largoParSinAlero()->mm)->toBe(2236)
            ->and(techo()->largoDiagonal()->mm)->toBe(1118);
    });

    it('el tirante cruza la luz completa', function () {
        $d = (new DespieceTechumbreService)->despiezar(techo());

        expect($d->porRol(RolPieza::Tirante)[0]->largo->mm)->toBe(4000);
    });

    it('el pendolón mide lo que sube la cumbrera', function () {
        $d = (new DespieceTechumbreService)->despiezar(techo());

        expect($d->porRol(RolPieza::Pendolon)[0]->largo->mm)->toBe(1000);
    });
});

describe('cuántas piezas', function () {
    it('pone una cercha cada separación más la de cierre', function () {
        // 6 m cada 1 m: 6 tramos, 7 cerchas.
        expect((new DespieceTechumbreService)->cuantasCerchas(techo()))->toBe(7);
    });

    it('da seis piezas por cercha a dos aguas', function () {
        $d = (new DespieceTechumbreService)->despiezar(techo());

        expect($d->cantidadDe(RolPieza::Par))->toBe(14)          // 2 por cercha
            ->and($d->cantidadDe(RolPieza::Tirante))->toBe(7)
            ->and($d->cantidadDe(RolPieza::Pendolon))->toBe(7)
            ->and($d->cantidadDe(RolPieza::Diagonal))->toBe(14); // 2 por cercha
    });

    it('a una agua va la mitad de pares y diagonales', function () {
        // Luz más corta: con 4 m a una agua el par mide 4,64 y no sale de una
        // tira de 4, que es justamente lo que valida el servicio.
        $d = (new DespieceTechumbreService)->despiezar(techo([
            'aguas' => 1,
            'luz' => Medida::de(3, Unidad::Metro),
            'alero' => Medida::de(0.3, Unidad::Metro),
        ]));

        expect($d->cantidadDe(RolPieza::Par))->toBe(7)
            ->and($d->cantidadDe(RolPieza::Diagonal))->toBe(7)
            ->and($d->cantidadDe(RolPieza::Tirante))->toBe(7);
    });

    it('cuenta las costaneras sobre el par inclinado, no sobre la planta', function () {
        // Par de 2,795 m cada 0,80: 4 tramos, 5 filas por faldón, 10 en total.
        // Medido en planta darían 4 y el alero quedaría descubierto.
        expect((new DespieceTechumbreService)->filasDeCostanera(techo()))->toBe(5);

        $d = (new DespieceTechumbreService)->despiezar(techo());
        expect($d->cantidadDe(RolPieza::Costanera))->toBe(10);
    });

    it('la cumbrera sólo existe con dos faldones', function () {
        $dos = (new DespieceTechumbreService)->despiezar(techo());
        $una = (new DespieceTechumbreService)->despiezar(techo([
            'aguas' => 1,
            'luz' => Medida::de(3, Unidad::Metro),
            'alero' => Medida::de(0.3, Unidad::Metro),
        ]));

        expect($dos->cantidadDe(RolPieza::Cumbrera))->toBe(1)
            ->and($una->cantidadDe(RolPieza::Cumbrera))->toBe(0);
    });
});

describe('superficie a cubrir', function () {
    it('incluye el vuelo por los cuatro costados', function () {
        // (6,00 + 2 x 0,50) de largo x 2,795 de faldón x 2 aguas = 39,13 m2.
        expect(techo()->superficieM2())->toBe(39.13);
    });

    it('el despiece informa la misma superficie que la configuración', function () {
        // Si se separan, las capas se cotizarían sobre un techo distinto del que
        // se está construyendo.
        $d = (new DespieceTechumbreService)->despiezar(techo());

        expect($d->superficieBrutaM2)->toBe(techo()->superficieM2());
    });
});

describe('costaneras y cumbrera se empalman', function () {
    it('van por metros lineales y no por tiras enteras', function () {
        // Corren sobre las cerchas, así que empalman encima de una: lo que
        // importa son los metros, igual que en las soleras del muro.
        $d = (new DespieceTechumbreService)->despiezar(techo());
        $plan = (new PlanCorteService)->para($d->piezas, techo()->corte);

        expect(RolPieza::Costanera->esCorrida())->toBeTrue()
            ->and(RolPieza::Cumbrera->esCorrida())->toBeTrue()
            // 10 costaneras de 6 m más la cumbrera: 66 metros lineales.
            ->and($plan->metrosCorridas)->toBe(66.0);
    });
});

describe('geometría que no cierra', function () {
    it('rechaza un tirante que no sale de una tira', function () {
        // Empalmar a media luz es justo donde el tirante más trabaja a tracción.
        (new DespieceTechumbreService)->despiezar(techo([
            'luz' => Medida::de(5, Unidad::Metro),
            'corte' => new ParametrosCorte(Medida::de(4, Unidad::Metro)),
        ]));
    })->throws(UnprocessableEntityHttpException::class, 'no se puede empalmar a media luz');

    it('rechaza una pendiente donde el agua se empoza', function () {
        (new DespieceTechumbreService)->despiezar(techo(['alturaCumbrera' => Medida::desdeMm(50)]));
    })->throws(UnprocessableEntityHttpException::class, 'el agua no corre');

    it('rechaza un techo plano', function () {
        techo(['alturaCumbrera' => Medida::cero()]);
    })->throws(InvalidArgumentException::class, 'queda plano');

    it('no admite más de dos aguas', function () {
        techo(['aguas' => 4]);
    })->throws(InvalidArgumentException::class, 'una o dos aguas');
});
