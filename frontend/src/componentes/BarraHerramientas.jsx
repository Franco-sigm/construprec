/**
 * Barra de pestañas inferior, con una herramienta por sección.
 *
 * Los iconos son SVG dibujados a mano y no una fuente de iconos: son tres, y
 * cargar una tipografía entera de miles de glifos para usar tres sería pagar
 * mucho por muy poco.
 */

function Nivel() {
    return (
        <svg viewBox="0 0 40 18" width="38" height="18" aria-hidden="true">
            <rect x="1" y="3" width="38" height="12" rx="2" fill="#e0b849" stroke="#8a6134" strokeWidth="1.5" />
            <rect x="15" y="6" width="10" height="6" rx="1" fill="#bfe3a0" stroke="#5d7a45" strokeWidth="1" />
            <circle cx="20" cy="9" r="1.6" fill="#6fae4a" />
        </svg>
    );
}

function Martillo() {
    return (
        <svg viewBox="0 0 32 26" width="32" height="26" aria-hidden="true">
            <path d="M6 4 h13 l3 5 h-4 l-2 -2 h-10 z" fill="#7d848d" stroke="#383d44" strokeWidth="1.4" />
            <path d="M13 9 l4 14 h-4 l-3 -14 z" fill="#c19257" stroke="#8a6134" strokeWidth="1.4" />
        </svg>
    );
}

function Huincha() {
    return (
        <svg viewBox="0 0 30 26" width="30" height="26" aria-hidden="true">
            <rect x="2" y="6" width="20" height="16" rx="4" fill="#e0b849" stroke="#8a6134" strokeWidth="1.5" />
            <circle cx="12" cy="14" r="4.5" fill="#f4f1e7" stroke="#8a6134" strokeWidth="1.4" />
            <path d="M22 10 h6 v5 h-6 z" fill="#f4f1e7" stroke="#8a6134" strokeWidth="1.4" />
        </svg>
    );
}

const SECCIONES = [
    { clave: 'proyecto', rotulo: 'Proyecto', Icono: Nivel },
    { clave: 'etapas', rotulo: 'Etapas', Icono: Martillo },
    { clave: 'materiales', rotulo: 'Materiales', Icono: Huincha },
];

export default function BarraHerramientas({ activa, onCambiar }) {
    return (
        <nav
            aria-label="Secciones"
            style={{ display: 'flex', justifyContent: 'center', pointerEvents: 'none' }}
        >
            <div
                className="veta"
                style={{
                    display: 'flex',
                    gap: 4,
                    padding: '6px 10px 0',
                    border: '3px solid var(--madera-borde)',
                    borderBottom: 0,
                    borderRadius: '10px 10px 0 0',
                    boxShadow: '0 -3px 10px rgba(40, 25, 10, 0.35)',
                    pointerEvents: 'auto',
                }}
            >
                {SECCIONES.map(({ clave, rotulo, Icono }) => {
                    const esActiva = clave === activa;
                    return (
                        <button
                            key={clave}
                            type="button"
                            onClick={() => onCambiar(clave)}
                            aria-current={esActiva ? 'page' : undefined}
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                gap: 2,
                                minWidth: 96,
                                padding: '6px 14px 10px',
                                border: 0,
                                background: 'transparent',
                                cursor: 'pointer',
                                fontFamily: 'var(--fuente-texto)',
                                fontSize: '0.9rem',
                                color: 'var(--tinta)',
                                // La pestaña activa se subraya en vez de cambiar de
                                // color: el subrayado se ve igual de bien para quien
                                // no distingue matices de marrón.
                                borderBottom: esActiva ? '3px solid var(--tinta)' : '3px solid transparent',
                                opacity: esActiva ? 1 : 0.72,
                            }}
                        >
                            <Icono />
                            {rotulo}
                        </button>
                    );
                })}
            </div>
        </nav>
    );
}
