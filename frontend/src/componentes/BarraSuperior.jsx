import Boton from './Boton';

/**
 * Formatea una fecha ISO en horario local.
 *
 * `new Date('2026-09-06')` se interpreta como medianoche UTC, y al mostrarla en
 * Chile queda un dia antes. Partir la cadena a mano evita ese corrimiento sin
 * arrastrar una libreria de fechas para un solo formato.
 */
function formatear(iso) {
    const [anio, mes, dia] = iso.split('-').map(Number);

    return new Date(anio, mes - 1, dia).toLocaleDateString('es-CL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

export default function BarraSuperior({ titulo, fecha, onVolver, usuario, onEntrar, onSalir }) {
    return (
        <header
            className="veta-oscura"
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 12,
                padding: '8px 14px',
                borderBottom: '3px solid var(--madera-borde)',
                boxShadow: '0 3px 10px rgba(30, 18, 6, 0.45)',
            }}
        >
            <Boton onClick={onVolver} style={{ width: 'auto' }}>
                ‹ {titulo}
            </Boton>

            <span style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                {usuario ? (
                    <>
                        <span className="titulo" style={{ fontSize: '0.8rem', opacity: 0.9 }}>
                            {usuario.nombre}
                        </span>
                        <Boton onClick={onSalir} style={{ width: 'auto' }}>Salir</Boton>
                    </>
                ) : (
                    <Boton onClick={onEntrar} style={{ width: 'auto' }}>Entrar</Boton>
                )}

            <time
                className="titulo"
                dateTime={fecha}
                style={{
                    background: 'var(--madera-clara)',
                    border: '2px solid var(--madera-borde)',
                    borderRadius: 'var(--radio-chico)',
                    padding: '9px 16px',
                    fontSize: '0.95rem',
                    boxShadow: 'inset 0 2px 0 rgba(255,240,210,.45), 0 2px 4px rgba(40,25,10,.35)',
                }}
            >
                {formatear(fecha)}
            </time>
            </span>
        </header>
    );
}
