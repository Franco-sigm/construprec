<?php

namespace App\Services\Capas;

/**
 * Sobre que caras del tabique se aplica la capa.
 */
enum AplicacionCapa: string
{
    /** Solo la cara que da al exterior. */
    case Exterior = 'exterior';

    /** Solo la cara que da al interior. */
    case Interior = 'interior';

    /** Las dos caras. Un tabique divisorio lleva revestimiento por ambos lados. */
    case Ambas = 'ambas';

    /**
     * Por cuanto se multiplica la superficie del muro.
     *
     * Sin esto un divisorio quedaria cotizado a la mitad: tiene un solo muro pero
     * dos caras que revestir.
     */
    public function factor(): int
    {
        return $this === self::Ambas ? 2 : 1;
    }
}
