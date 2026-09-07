<?php

namespace App\Services\Techumbre;

use App\Services\Madera\ParametrosCorte;
use App\Support\Medida;
use InvalidArgumentException;

/**
 * Como se arma la techumbre: cuantas aguas, que pendiente y cada cuanto van las
 * cerchas.
 *
 * La pendiente se define por la ALTURA DE CUMBRERA y no por un angulo. Es lo que
 * se decide primero en obra —cuanto puede subir el techo sobre el muro— y el
 * angulo sale solo. Pedirlo al reves obliga a calcular a mano para saber si el
 * techo cabe bajo el limite de altura que uno tiene.
 */
final readonly class ConfiguracionTechumbre
{
    public function __construct(
        /** 1 (un faldon) o 2 (a dos aguas, con caballete al centro). */
        public int $aguas,

        /** Distancia entre los muros que reciben la techumbre. */
        public Medida $luz,

        /** Largo del techo en el sentido del caballete. */
        public Medida $largo,

        /** Cuanto sube el caballete por sobre el apoyo. De aca sale la pendiente. */
        public Medida $alturaCumbrera,

        /** Escuadria de par y tirante. */
        public Medida $escuadriaAncho,
        public Medida $escuadriaAlto,

        public Medida $separacionCerchas,
        public Medida $separacionCostaneras,

        /** Escuadria de las costaneras, que suelen ser mas livianas que la cercha. */
        public Medida $costaneraAncho,
        public Medida $costaneraAlto,

        /**
         * Vuelo del techo mas alla del muro, medido en proyeccion horizontal.
         *
         * Se mide en horizontal y no siguiendo la pendiente porque es asi como se
         * decide y como se mira desde abajo: "sesenta centimetros de alero". El
         * largo real del par se calcula despues.
         */
        public Medida $alero,

        public ParametrosCorte $corte,
    ) {
        if ($aguas < 1 || $aguas > 2) {
            throw new InvalidArgumentException('La techumbre admite una o dos aguas.');
        }

        if ($luz->esCero() || $largo->esCero()) {
            throw new InvalidArgumentException('La techumbre necesita luz y largo mayores que cero.');
        }

        if ($alturaCumbrera->esCero()) {
            throw new InvalidArgumentException(
                'Sin altura de cumbrera el techo queda plano y el agua no corre.'
            );
        }

        if ($separacionCerchas->esCero() || $separacionCostaneras->esCero()) {
            throw new InvalidArgumentException('Las separaciones no pueden ser cero.');
        }
    }

    /**
     * Lo que avanza el par en horizontal desde el apoyo hasta el caballete.
     *
     * En dos aguas cada faldon cubre la mitad de la luz; en una, el faldon unico
     * la cubre entera.
     */
    public function avanceHorizontal(): Medida
    {
        return $this->aguas === 2 ? $this->luz->por(0.5) : $this->luz;
    }

    /** Cuanto sube el techo por cada metro que avanza. */
    public function pendiente(): float
    {
        return round($this->alturaCumbrera->mm / $this->avanceHorizontal()->mm, 6);
    }

    public function pendientePorcentaje(): float
    {
        return round($this->pendiente() * 100, 2);
    }

    public function pendienteGrados(): float
    {
        return round(rad2deg(atan($this->pendiente())), 2);
    }

    /**
     * Cuanto se alarga una distancia horizontal al seguir la pendiente.
     *
     * Es la hipotenusa por cada unidad de avance. Un techo de 30% estira un 4,4%
     * todo lo que se mida sobre el faldon, y olvidarlo deja los pares cortos.
     */
    public function factorInclinacion(): float
    {
        return sqrt(1 + $this->pendiente() ** 2);
    }

    /** Largo de corte del par: avance mas alero, ya estirado por la pendiente. */
    public function largoPar(): Medida
    {
        return $this->avanceHorizontal()->mas($this->alero)->por($this->factorInclinacion());
    }

    /** El par sin alero. Sirve para el largo de las diagonales. */
    public function largoParSinAlero(): Medida
    {
        return $this->avanceHorizontal()->por($this->factorInclinacion());
    }

    /**
     * Largo de corte de una diagonal.
     *
     * Va del pie del pendolon a la mitad del par, y por semejanza de triangulos
     * eso es exactamente la mitad del par sin alero. No hay que calcularlo con
     * Pitagoras: sale de la geometria de la cercha.
     */
    public function largoDiagonal(): Medida
    {
        return $this->largoParSinAlero()->por(0.5);
    }

    /** Superficie de faldon a cubrir, incluido el vuelo por los cuatro costados. */
    public function superficieM2(): float
    {
        return round(
            $this->aguas * $this->largo->mas($this->alero->por(2))->metros() * $this->largoPar()->metros(),
            4,
        );
    }
}
