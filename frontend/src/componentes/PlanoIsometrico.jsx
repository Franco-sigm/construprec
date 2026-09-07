/**
 * Dibujo isométrico del esqueleto del proyecto.
 *
 * No es una ilustración: se genera desde las mismas medidas con que se calcula
 * el presupuesto, así que los pies derechos que se ven son los que se van a
 * comprar. Si el usuario cambia la separación de 40 a 60 cm, el dibujo cambia
 * con él, y eso hace visible un error de tipeo que en una tabla de números
 * pasaría inadvertido.
 */

// Proyección isométrica clásica: los ejes horizontales se abren 30 grados a
// cada lado y la altura sube recta. Es la misma que usa el dibujo técnico a
// mano, y por eso "se lee" como un plano y no como una foto.
const COS30 = Math.cos(Math.PI / 6);

function proyectar(x, y, z) {
    return {
        x: (x - y) * COS30,
        y: (x + y) * 0.5 - z,
    };
}

function punto(x, y, z) {
    const p = proyectar(x, y, z);
    return `${p.x},${p.y}`;
}

/**
 * Los cuatro muros, cada uno con su recorrido y su normal hacia afuera.
 * El orden importa: se pintan de atrás hacia adelante para que los de adelante
 * tapen a los de atrás, que es lo único que da sensación de volumen sin
 * calcular oclusión de verdad.
 */
function murosDe(largo, ancho) {
    return [
        { clave: 'fondo', desde: [0, 0], hasta: [largo, 0], largo },
        { clave: 'izquierda', desde: [0, 0], hasta: [0, ancho], largo: ancho },
        { clave: 'derecha', desde: [largo, 0], hasta: [largo, ancho], largo: ancho },
        { clave: 'frente', desde: [0, ancho], hasta: [largo, ancho], largo },
    ];
}

function posicionesDePiesDerechos(largoMuro, separacion) {
    const posiciones = [];
    for (let d = 0; d < largoMuro; d += separacion) {
        posiciones.push(d);
    }
    posiciones.push(largoMuro);
    return posiciones;
}

function Muro({ muro, alto, separacion, trazo, tenue }) {
    const [x0, y0] = muro.desde;
    const [x1, y1] = muro.hasta;

    // Vector unitario a lo largo del muro, para poder recorrerlo en milímetros
    // sin preocuparse de en qué eje va.
    const dx = (x1 - x0) / muro.largo;
    const dy = (y1 - y0) / muro.largo;

    const opacidad = tenue ? 0.7 : 1;

    return (
        <g opacity={opacidad}>
            {/* Solera inferior y superior */}
            <polyline
                points={`${punto(x0, y0, 0)} ${punto(x1, y1, 0)}`}
                stroke="#8a6134"
                strokeWidth={trazo * 1.6}
                strokeLinecap="round"
                fill="none"
            />
            <polyline
                points={`${punto(x0, y0, alto)} ${punto(x1, y1, alto)}`}
                stroke="#8a6134"
                strokeWidth={trazo * 1.6}
                strokeLinecap="round"
                fill="none"
            />

            {/* Pies derechos, uno cada `separacion` más el de cierre */}
            {posicionesDePiesDerechos(muro.largo, separacion).map((d) => {
                const px = x0 + dx * d;
                const py = y0 + dy * d;
                return (
                    <line
                        key={d}
                        x1={proyectar(px, py, 0).x}
                        y1={proyectar(px, py, 0).y}
                        x2={proyectar(px, py, alto).x}
                        y2={proyectar(px, py, alto).y}
                        stroke={tenue ? "#b9884a" : "#c99a5c"}
                        strokeWidth={trazo}
                        strokeLinecap="round"
                    />
                );
            })}
        </g>
    );
}

function Cota({ desde, hasta, texto, trazo, desplazamiento = [0, 0] }) {
    const a = proyectar(...desde);
    const b = proyectar(...hasta);
    const [ox, oy] = desplazamiento;

    return (
        <g stroke="#4a90c2" fill="#4a90c2">
            <line
                x1={a.x + ox}
                y1={a.y + oy}
                x2={b.x + ox}
                y2={b.y + oy}
                strokeWidth={trazo * 0.5}
                markerStart="url(#punta)"
                markerEnd="url(#punta)"
            />
            <text
                x={(a.x + b.x) / 2 + ox}
                y={(a.y + b.y) / 2 + oy - trazo * 2}
                textAnchor="middle"
                stroke="none"
                fontSize={trazo * 6}
                fontFamily="'Roboto Condensed', sans-serif"
                fontWeight="600"
            >
                {texto}
            </text>
        </g>
    );
}

export default function PlanoIsometrico({
    largoMm = 6000,
    anchoMm = 4000,
    altoMm = 2400,
    separacionMm = 400,
}) {
    const muros = murosDe(largoMm, anchoMm);

    // Todo el grosor de linea se deriva de la dimension mayor. El viewBox esta en
    // milimetros, asi que un numero fijo daria trazos invisibles en una planta
    // grande y trazos gruesos como muros en una chica.
    const trazo = Math.max(largoMm, anchoMm, altoMm) / 110;

    // El recuadro se calcula desde las esquinas proyectadas y no a ojo: así el
    // dibujo queda encuadrado para cualquier planta, no solo para la de prueba.
    const esquinas = [
        [0, 0, 0], [largoMm, 0, 0], [largoMm, anchoMm, 0], [0, anchoMm, 0],
        [0, 0, altoMm], [largoMm, 0, altoMm], [largoMm, anchoMm, altoMm], [0, anchoMm, altoMm],
    ].map(([x, y, z]) => proyectar(x, y, z));

    const margen = Math.max(largoMm, anchoMm) * 0.22;
    const minX = Math.min(...esquinas.map((p) => p.x)) - margen;
    const maxX = Math.max(...esquinas.map((p) => p.x)) + margen;
    const minY = Math.min(...esquinas.map((p) => p.y)) - margen;
    const maxY = Math.max(...esquinas.map((p) => p.y)) + margen * 1.4;

    const metros = (mm) => `${(mm / 1000).toFixed(2)} m`;

    return (
        <svg
            viewBox={`${minX} ${minY} ${maxX - minX} ${maxY - minY}`}
            style={{ width: '100%', height: '100%', display: 'block' }}
            role="img"
            aria-label={`Esqueleto de ${metros(largoMm)} por ${metros(anchoMm)} y ${metros(altoMm)} de alto, con pies derechos cada ${separacionMm} milímetros`}
        >
            <defs>
                <marker id="punta" markerWidth="8" markerHeight="8" refX="4" refY="4" orient="auto" markerUnits="strokeWidth">
                    <path d="M0,1 L7,4 L0,7 z" fill="#4a90c2" />
                </marker>
            </defs>

            {/* Terreno: el plano azul sobre el que se apoya la construccion.
                Se dibuja mas grande que la planta para que se lea como suelo y
                no como una sombra pegada al borde. */}
            <polygon
                points={[
                    punto(-margen * 0.5, -margen * 0.5, 0),
                    punto(largoMm + margen * 0.5, -margen * 0.5, 0),
                    punto(largoMm + margen * 0.5, anchoMm + margen * 0.5, 0),
                    punto(-margen * 0.5, anchoMm + margen * 0.5, 0),
                ].join(' ')}
                fill="var(--plano-azul-suave)"
                opacity="0.5"
            />

            {/* Radier: mas claro que la madera para que los pies derechos que
                pasan por delante se distingan del piso que tienen detras. */}
            <polygon
                points={[
                    punto(0, 0, 0),
                    punto(largoMm, 0, 0),
                    punto(largoMm, anchoMm, 0),
                    punto(0, anchoMm, 0),
                ].join(' ')}
                fill="#efdcbb"
                stroke="#a87c4e"
                strokeWidth={trazo * 0.6}
            />

            {/* Entablado del piso, corriendo a lo largo */}
            {Array.from({ length: Math.floor(anchoMm / 500) }, (_, i) => (i + 1) * 500).map((y) => (
                <line
                    key={y}
                    x1={proyectar(0, y, 0).x}
                    y1={proyectar(0, y, 0).y}
                    x2={proyectar(largoMm, y, 0).x}
                    y2={proyectar(largoMm, y, 0).y}
                    stroke="#d3b184"
                    strokeWidth={trazo * 0.25}
                />
            ))}

            {/* Muros de atrás primero, para que los de adelante los tapen */}
            {muros.slice(0, 2).map((muro) => (
                <Muro key={muro.clave} muro={muro} alto={altoMm} separacion={separacionMm} trazo={trazo} tenue />
            ))}
            {muros.slice(2).map((muro) => (
                <Muro key={muro.clave} muro={muro} alto={altoMm} separacion={separacionMm} trazo={trazo} />
            ))}

            <Cota desde={[0, anchoMm, 0]} hasta={[largoMm, anchoMm, 0]} texto={metros(largoMm)} trazo={trazo} desplazamiento={[0, trazo * 11]} />
            <Cota desde={[largoMm, 0, 0]} hasta={[largoMm, anchoMm, 0]} texto={metros(anchoMm)} trazo={trazo} desplazamiento={[trazo * 11, trazo * 6]} />
            <Cota desde={[largoMm, anchoMm, 0]} hasta={[largoMm, anchoMm, altoMm]} texto={metros(altoMm)} trazo={trazo} desplazamiento={[trazo * 13, 0]} />
        </svg>
    );
}
