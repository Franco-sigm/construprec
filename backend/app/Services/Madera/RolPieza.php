<?php

namespace App\Services\Madera;

/**
 * Que funcion cumple cada pieza dentro del tabique.
 *
 * Importa mas alla de la etiqueta: dos piezas del mismo largo pero distinto rol
 * se cortan igual, pero el usuario necesita ver por que las esta comprando. Un
 * presupuesto que dice "48 piezas de 2,32 m" no se puede revisar; uno que dice
 * "32 pies derechos, 8 jambas, 8 pies derechos de apoyo" si.
 */
enum RolPieza: string
{
    case PieDerecho = 'pie_derecho';
    case SoleraInferior = 'solera_inferior';
    case SoleraSuperior = 'solera_superior';

    /** Pie derecho completo que enmarca un vano por fuera. */
    case Jamba = 'jamba';

    /** Pie derecho cortado que sostiene el dintel por debajo. */
    case PieDerechoApoyo = 'pie_derecho_apoyo';

    /** Viga sobre el vano: traspasa a las jambas la carga que el vano interrumpe. */
    case Dintel = 'dintel';

    /** Horizontal bajo la ventana, donde apoya el marco. */
    case Alfeizar = 'alfeizar';

    /** Tramo corto entre el alfeizar y la solera inferior. */
    case PieDerechoBajoVano = 'pie_derecho_bajo_vano';

    /** Tramo corto entre el dintel y la solera superior. */
    case PieDerechoSobreVano = 'pie_derecho_sobre_vano';

    /**
     * Horizontal entre dos pies derechos, a media altura.
     *
     * Traba los pies derechos para que no pandeen y le da apoyo al canto de las
     * planchas de revestimiento. Es la pieza mas corta del tabique, asi que sale
     * del recorte que dejan los cortes largos en vez de consumir tiras nuevas.
     */
    case Cadeneta = 'cadeneta';

    /**
     * Pieza extra en el encuentro de dos muros.
     *
     * Donde se juntan dos caras ya hay dos pies derechos: el de cierre de una y
     * el de arranque de la otra. Pero van perpendiculares entre si, no juntos, y
     * ninguno deja cara mirando hacia el rincon. El canto de la plancha que llega
     * ahi queda al aire, sin madera donde clavarse, y hay que resolverlo a medida
     * en obra.
     *
     * La solucion es poner dos pies derechos juntos en uno de los muros: el
     * segundo va pegado al primero y deja su cara hacia adentro. Eso es lo que
     * cuenta este rol.
     */
    case PosteEsquina = 'poste_esquina';

    // --- techumbre ---

    /** El inclinado que baja del caballete al alero. Define la pendiente. */
    case Par = 'par';

    /**
     * El horizontal que une los dos pares de muro a muro.
     *
     * Es lo que impide que el techo se abra: sin el, cada par empuja su muro
     * hacia afuera y la cercha se desarma sola.
     */
    case Tirante = 'tirante';

    /** El vertical del centro, del tirante al caballete. */
    case Pendolon = 'pendolon';

    /** Las dos que van del pie del pendolon a la mitad de cada par. */
    case Diagonal = 'diagonal';

    /** Las horizontales que cruzan las cerchas y reciben la cubierta. */
    case Costanera = 'costanera';

    /** La viga del caballete, donde se encuentran los dos faldones. */
    case Cumbrera = 'cumbrera';

    /**
     * Si la pieza es una corrida que admite empalme.
     *
     * Distingue como se compra. Una solera de 6 m se arma con dos tiras de 3,2 m
     * empalmadas sobre un pie derecho, y ahi lo que importa son los metros
     * lineales totales. Un pie derecho de 2,32 m tiene que salir entero de una
     * tira: no se puede empalmar a media altura sin perder la resistencia que
     * justifica ponerlo. Tratar los dos casos igual sobreestima o subestima la
     * compra segun cual sea.
     */
    public function esCorrida(): bool
    {
        return match ($this) {
            // Las soleras se empalman sobre un pie derecho, y las costaneras y la
            // cumbrera sobre una cercha: en los tres casos lo que importa son los
            // metros lineales y no de que largo es cada tramo.
            self::SoleraInferior, self::SoleraSuperior, self::Costanera, self::Cumbrera => true,
            default => false,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::PieDerecho => 'Pie derecho',
            self::SoleraInferior => 'Solera inferior',
            self::SoleraSuperior => 'Solera superior',
            self::Jamba => 'Jamba de vano',
            self::PieDerechoApoyo => 'Pie derecho de apoyo',
            self::Dintel => 'Dintel',
            self::Alfeizar => 'Alfeizar',
            self::PieDerechoBajoVano => 'Pie derecho bajo vano',
            self::PieDerechoSobreVano => 'Pie derecho sobre vano',
            self::Cadeneta => 'Cadeneta',
            self::PosteEsquina => 'Poste de esquina',
            self::Par => 'Par',
            self::Tirante => 'Tirante',
            self::Pendolon => 'Pendolón',
            self::Diagonal => 'Diagonal',
            self::Costanera => 'Costanera',
            self::Cumbrera => 'Cumbrera',
        };
    }
}
