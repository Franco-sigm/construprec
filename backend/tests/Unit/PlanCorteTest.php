<?php

use App\Services\Tabiqueria\ConfiguracionTabique;
use App\Services\Tabiqueria\Despiece;
use App\Services\Tabiqueria\DespieceService;
use App\Services\Tabiqueria\Pieza;
use App\Services\Tabiqueria\PlanCorteService;
use App\Services\Tabiqueria\RolPieza;
use App\Services\Tabiqueria\Vano;
use App\Support\Medida;
use App\Support\Unidad;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

function conf(array $sobrescribir = []): ConfiguracionTabique
{
    return new ConfiguracionTabique(...array_merge([
        'escuadriaAncho' => Medida::desdeMm(41),
        'escuadriaAlto' => Medida::desdeMm(65),
        'separacion' => Medida::de(0.4, Unidad::Metro),
        'largoComercial' => Medida::de(3.2, Unidad::Metro),
    ], $sobrescribir));
}

function metros(float $v): Medida
{
    return Medida::de($v, Unidad::Metro);
}

describe('piezas que salen enteras de una tira', function () {
    it('mete una sola pieza larga por tira cuando no cabe otra', function () {
        // Dos pies derechos de 2,318 no caben juntos en 3,2 m.
        $plan = (new PlanCorteService)->para(
            [new Pieza(RolPieza::PieDerecho, metros(2.318), 2)],
            conf(),
        );

        expect($plan->tiras)->toHaveCount(2);
    });

    it('aprovecha el recorte metiendo una pieza corta junto a una larga', function () {
        // 2,318 + 0,859 = 3,177 y cabe en 3,2. Es el caso que justifica empaquetar
        // en vez de calcular cada largo por separado.
        $plan = (new PlanCorteService)->para([
            new Pieza(RolPieza::PieDerecho, metros(2.318), 1),
            new Pieza(RolPieza::PieDerechoBajoVano, metros(0.859), 1),
        ], conf());

        expect($plan->tiras)->toHaveCount(1)
            ->and($plan->tiras[0]->cortes)->toHaveCount(2)
            // 3200 - 2318 - 859 = 23 mm de madera, menos los 6 que se lleva la
            // sierra en los dos cortes.
            ->and($plan->tiras[0]->sobrante()->mm)->toBe(17)
            ->and($plan->tiras[0]->aserrinMm())->toBe(6);
    });

    it('coloca de mayor a menor para que lo corto rellene y no al reves', function () {
        // Si entrara primero lo corto, las tres piezas de 1,1 ocuparian una tira
        // y la de 3,0 necesitaria otra: 2 tiras. Ordenando al reves, tambien 2,
        // pero con el recorte concentrado y utilizable.
        $plan = (new PlanCorteService)->para([
            new Pieza(RolPieza::PieDerechoBajoVano, metros(1.1), 3),
            new Pieza(RolPieza::PieDerecho, metros(3.0), 1),
        ], conf());

        expect($plan->tiras[0]->cortes[0]->largo->metros())->toBe(3.0);
    });

    it('mete tres piezas de un metro en la misma tira', function () {
        $plan = (new PlanCorteService)->para(
            [new Pieza(RolPieza::PieDerechoBajoVano, metros(1.0), 3)],
            conf(),
        );

        expect($plan->tiras)->toHaveCount(1)
            // 3,2 - 3,0 = 0,2 m, menos los 9 mm de tres pasadas de sierra.
            ->and($plan->tiras[0]->sobrante()->metros())->toBe(0.191);
    });

    it('rechaza una pieza mas larga que la tira', function () {
        (new PlanCorteService)->para(
            [new Pieza(RolPieza::PieDerecho, metros(3.5), 1)],
            conf(),
        );
    })->throws(UnprocessableEntityHttpException::class, 'no sale de una tira');
});

describe('soleras, que admiten empalme', function () {
    it('cuenta las soleras por metros lineales y no por tiras enteras', function () {
        // Cuatro soleras de 4 m son 16 ml. Por pieza serian 4 x ceil(4/3,2) = 8
        // tiras; empalmando, ceil(16/3,2) = 5.
        $plan = (new PlanCorteService)->para(
            [new Pieza(RolPieza::SoleraInferior, metros(4), 4)],
            conf(),
        );

        expect($plan->metrosCorridas)->toBe(16.0)
            ->and($plan->tirasCorridas)->toBe(5)
            ->and($plan->tiras)->toBeEmpty();
    });

    it('no mezcla soleras con las piezas empaquetadas', function () {
        $plan = (new PlanCorteService)->para([
            new Pieza(RolPieza::SoleraInferior, metros(6), 1),
            new Pieza(RolPieza::PieDerecho, metros(2.318), 1),
        ], conf());

        expect($plan->tirasCorridas)->toBe(2)
            ->and($plan->tiras)->toHaveCount(1)
            ->and($plan->tirasNetas())->toBe(3);
    });
});

describe('merma', function () {
    it('no cambia nada cuando es cero', function () {
        $plan = (new PlanCorteService)->para(
            [new Pieza(RolPieza::PieDerecho, metros(2.318), 10)],
            conf(),
        );

        expect($plan->tirasNetas())->toBe(10)
            ->and($plan->tirasAComprar())->toBe(10);
    });

    it('se aplica sobre el total y redondea hacia arriba', function () {
        // 10 tiras con 10% son 11.
        $plan = (new PlanCorteService)->para(
            [new Pieza(RolPieza::PieDerecho, metros(2.318), 10)],
            conf(['mermaPct' => 10.0]),
        );

        expect($plan->tirasAComprar())->toBe(11);
    });

    it('nunca compra menos de una tira de mas cuando hay merma', function () {
        // 10 tiras con 5% son 10,5 -> 11. Redondear a la baja dejaria la obra corta.
        $plan = (new PlanCorteService)->para(
            [new Pieza(RolPieza::PieDerecho, metros(2.318), 10)],
            conf(['mermaPct' => 5.0]),
        );

        expect($plan->tirasAComprar())->toBe(11);
    });
});

describe('caso completo, planta de 6 x 4', function () {
    it('resuelve el plan de corte de las cuatro caras', function () {
        $s = new DespieceService;
        $config = conf();

        $t = Despiece::combinar(
            $s->deCara(metros(6), metros(2.4), [Vano::puerta(metros(0.9), metros(2.0))], $config),
            $s->deCara(metros(6), metros(2.4), [], $config),
            $s->deCara(metros(4), metros(2.4), [], $config),
            $s->deCara(metros(4), metros(2.4), [], $config),
        );

        $plan = (new PlanCorteService)->para($t->piezas, $config);

        // Las soleras suman 40 ml (2x6 + 2x4, por inferior y superior).
        expect($plan->metrosCorridas)->toBe(40.0)
            ->and($plan->tirasCorridas)->toBe(13)
            // Ninguna tira puede quedar con sobrante negativo ni exceder su largo.
            ->and(array_filter($plan->tiras, fn ($t) => $t->ocupado()->mm > 3200))->toBeEmpty();
    });
});

describe('lo que antes se estimaba a ojo y ahora se calcula', function () {
    it('cuenta la madera que se lleva la sierra', function () {
        // Cada pasada convierte unos milímetros en aserrín. Es poco, pero es la
        // diferencia entre que la última pieza quepa o no quepa.
        $plan = (new PlanCorteService)->para(
            [new Pieza(RolPieza::PieDerecho, metros(1.0), 9)],
            conf(),
        );

        expect($plan->cortesDeSierra())->toBe(9)
            ->and($plan->aserrinM())->toBe(0.027);
    });

    it('se puede desactivar poniendo el ancho de corte en cero', function () {
        // Sirve para contrastar contra un cálculo hecho a mano, donde nadie
        // descuenta el disco.
        $plan = (new PlanCorteService)->para(
            [new Pieza(RolPieza::PieDerecho, metros(1.0), 3)],
            conf(['anchoCorteMm' => 0]),
        );

        expect($plan->aserrinM())->toBe(0.0)
            ->and($plan->tiras[0]->sobrante()->metros())->toBe(0.2);
    });

    it('informa qué porcentaje del material comprado se pierde de verdad', function () {
        // Es el número que antes se pedía a ojo. Sirve para contrastar: si
        // alguien pone 5% de descarte y la pérdida real ya va en 15%, conviene
        // revisar el largo comercial antes que subir el porcentaje.
        $plan = (new PlanCorteService)->para(
            [new Pieza(RolPieza::PieDerecho, metros(2.318), 10)],
            conf(),
        );

        expect($plan->perdidaCalculadaPct())->toBeGreaterThan(20.0)
            ->and($plan->perdidaCalculadaPct())->toBeLessThan(30.0);
    });
});
