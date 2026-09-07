/**
 * Botón tallado en madera.
 *
 * Es un <button> de verdad y no un div con onClick: así responde al teclado, al
 * lector de pantalla y al Enter sin que haya que reimplementar nada de eso.
 */
export default function Boton({ principal = false, className = '', children, ...resto }) {
    return (
        <button
            type="button"
            className={`boton veta ${principal ? 'boton--principal' : ''} ${className}`}
            {...resto}
        >
            {children}
        </button>
    );
}
