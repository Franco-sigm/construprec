/**
 * Tabla de madera con marco y escuadras metálicas en las cuatro esquinas.
 *
 * Las escuadras son decorativas, así que se marcan aria-hidden: quien navega
 * con lector de pantalla no gana nada oyendo "esquina, esquina, esquina".
 */
export default function Panel({ veta = 'veta', escuadras = true, className = '', children, ...resto }) {
    return (
        <div className={`panel ${veta} ${escuadras ? 'escuadras' : ''} ${className}`} {...resto}>
            {escuadras && (
                <>
                    <span className="escuadra escuadra--ai" aria-hidden="true" />
                    <span className="escuadra escuadra--ad" aria-hidden="true" />
                </>
            )}
            {children}
        </div>
    );
}
