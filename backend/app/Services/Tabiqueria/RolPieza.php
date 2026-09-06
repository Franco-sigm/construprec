<?php

namespace App\Services\Tabiqueria;

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
            self::SoleraInferior, self::SoleraSuperior => true,
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
        };
    }
}
