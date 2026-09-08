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

/**
 * Ubica los vanos a lo largo del muro, apoyados en la trama.
 *
 * La posición no es un dato del presupuesto —dos ventanas de 1,20 cuestan lo
 * mismo estén donde estén— así que se reparten con espacios parejos y se corre
 * cada una al pie derecho más cercano. El dibujo es esquemático en el DÓNDE,
 * pero exacto en el CUÁNTO: si el vano no calza con la trama, se ve el tramo
 * residual angosto que queda al lado, que es justamente lo que hay que notar.
 */
function ubicarVanos(vanos, largoMuro, separacion, espesor) {
    const piezas = vanos.flatMap((v) => Array.from({ length: v.cantidad ?? 1 }, () => v));

    if (piezas.length === 0) return [];

    // Ancho que consume cada vano con su marco completo.
    const conMarco = piezas.map((v) => v.ancho_mm + 3 * espesor);
    const ocupado = conMarco.reduce((a, b) => a + b, 0);
    const holgura = Math.max(0, largoMuro - ocupado) / (piezas.length + 1);

    let cursor = holgura;

    return piezas.map((vano, i) => {
        // Si el usuario lo ubicó, manda su elección. Si no, se reparte y se corre
        // al pie derecho más cercano, que es lo que haría un carpintero.
        const inicio = vano.inicio_mm != null
            ? vano.inicio_mm
            : Math.round(cursor / separacion) * separacion;

        cursor += conMarco[i] + holgura;

        return {
            ...vano,
            desde: Math.max(0, Math.min(inicio, largoMuro - conMarco[i])),
            hasta: Math.max(0, Math.min(inicio + conMarco[i], largoMuro)),
        };
    });
}

/**
 * A qué alturas van las filas de cadenetas.
 *
 * Se reparten parejo entre solera y solera: una fila va a media altura, dos a
 * los tercios, tres a los cuartos. No es una convención inventada — el punto de
 * la cadeneta es acortar el tramo libre del pie derecho para que no pandee, y
 * eso se logra dividiéndolo en partes iguales.
 */
function alturasDeCadenetas(alto, filas) {
    return Array.from({ length: filas }, (_, i) => (alto * (i + 1)) / (filas + 1));
}

function posicionesDePiesDerechos(largoMuro, separacion) {
    const posiciones = [];
    for (let d = 0; d < largoMuro; d += separacion) {
        posiciones.push(d);
    }
    posiciones.push(largoMuro);
    return posiciones;
}

function Muro({ muro, alto, separacion, trazo, espesor, vanos = [], filasCadenetas = 0, tenue }) {
    const [x0, y0] = muro.desde;
    const [x1, y1] = muro.hasta;
    const ubicados = ubicarVanos(vanos, muro.largo, separacion, espesor);

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

            {/* Pies derechos, uno cada `separacion` más el de cierre. Los que caen
                dentro de un vano no existen: ahí va el hueco. */}
            {posicionesDePiesDerechos(muro.largo, separacion)
                .filter((d) => !ubicados.some((v) => d > v.desde + 1 && d < v.hasta - 1))
                .map((d) => {
                    const px = x0 + dx * d;
                    const py = y0 + dy * d;
                    return (
                        <line
                            key={d}
                            x1={proyectar(px, py, 0).x}
                            y1={proyectar(px, py, 0).y}
                            x2={proyectar(px, py, alto).x}
                            y2={proyectar(px, py, alto).y}
                            stroke={tenue ? '#b9884a' : '#c99a5c'}
                            strokeWidth={trazo}
                            strokeLinecap="round"
                        />
                    );
                })}

            {/* Cadenetas: una por espacio libre entre pies derechos y por fila.
                Donde hay vano no van —ahí traban el dintel y el alféizar— así que
                se saltan igual que se saltan los pies derechos dentro del hueco,
                y el corte se ve en el dibujo. */}
            {filasCadenetas > 0 && alturasDeCadenetas(alto, filasCadenetas).map((z) => {
                const pilares = posicionesDePiesDerechos(muro.largo, separacion);

                return pilares.slice(0, -1).map((desde, i) => {
                    const hasta = pilares[i + 1];
                    const medio = (desde + hasta) / 2;

                    if (ubicados.some((v) => medio > v.desde && medio < v.hasta)) {
                        return null;
                    }

                    return (
                        <line
                            key={`${z}-${desde}`}
                            x1={proyectar(x0 + dx * desde, y0 + dy * desde, z).x}
                            y1={proyectar(x0 + dx * desde, y0 + dy * desde, z).y}
                            x2={proyectar(x0 + dx * hasta, y0 + dy * hasta, z).x}
                            y2={proyectar(x0 + dx * hasta, y0 + dy * hasta, z).y}
                            stroke={tenue ? '#a97a42' : '#b98d52'}
                            strokeWidth={trazo * 0.8}
                            strokeLinecap="round"
                        />
                    );
                });
            })}

            {/* Marco de cada vano: jambas a los lados, dintel arriba y alféizar
                abajo en las ventanas. */}
            {ubicados.map((vano, i) => {
                const punto3 = (d, z) => punto(x0 + dx * d, y0 + dy * d, z);
                const arriba = vano.antepecho_mm + vano.alto_mm;

                return (
                    <g key={i} stroke="#8a6134" strokeWidth={trazo * 1.2} fill="none" strokeLinecap="round">
                        <polyline points={`${punto3(vano.desde, 0)} ${punto3(vano.desde, alto)}`} />
                        <polyline points={`${punto3(vano.hasta, 0)} ${punto3(vano.hasta, alto)}`} />
                        <polyline points={`${punto3(vano.desde, arriba)} ${punto3(vano.hasta, arriba)}`} />
                        {vano.antepecho_mm > 0 && (
                            <polyline points={`${punto3(vano.desde, vano.antepecho_mm)} ${punto3(vano.hasta, vano.antepecho_mm)}`} />
                        )}
                        {/* El hueco, para que se lea como abertura y no como reja. */}
                        <polygon
                            points={[
                                punto3(vano.desde, vano.antepecho_mm),
                                punto3(vano.hasta, vano.antepecho_mm),
                                punto3(vano.hasta, arriba),
                                punto3(vano.desde, arriba),
                            ].join(' ')}
                            fill="rgba(74, 144, 194, 0.16)"
                            stroke="none"
                        />
                    </g>
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

/**
 * Las cerchas, dibujadas sobre los muros.
 *
 * Los tirantes cruzan el ancho y las cerchas se repiten a lo largo, que es como
 * se arma: la cercha salva la luz corta y el techo corre en el otro sentido.
 *
 * Igual que con los muros, esto no es una ilustración: si el usuario sube la
 * cumbrera o junta las cerchas, el dibujo cambia. Ver el techo abrirse al mover
 * un número es lo que hace notar un error de tipeo que en una tabla pasaría.
 */
function Techumbre({ largo, ancho, altoMuro, techo, trazo }) {
    const { aguas, alturaCumbrera, alero, separacionCerchas, separacionCostaneras } = techo;

    // Cuánto sube el faldón por cada milímetro que avanza en horizontal.
    const avance = aguas === 2 ? ancho / 2 : ancho;
    const pendiente = alturaCumbrera / avance;

    // El alero cuelga más abajo del muro siguiendo la misma pendiente.
    const zAlero = altoMuro - alero * pendiente;
    const zCumbre = altoMuro + alturaCumbrera;

    const posiciones = [];
    for (let d = 0; d < largo; d += separacionCerchas) posiciones.push(d);
    posiciones.push(largo);

    // Perfil de la cercha en el plano transversal: del alero al caballete y de
    // vuelta. En una agua el caballete queda sobre el muro del fondo.
    const cumbreY = aguas === 2 ? ancho / 2 : ancho;
    const perfil = aguas === 2
        ? [[-alero, zAlero], [cumbreY, zCumbre], [ancho + alero, zAlero]]
        : [[-alero, zAlero], [cumbreY + alero, zCumbre + alero * pendiente]];

    const costaneras = [];
    const largoFaldon = Math.hypot(avance + alero, (avance + alero) * pendiente);
    const filas = Math.floor(largoFaldon / separacionCostaneras) + 1;

    for (let i = 0; i <= filas; i++) {
        const t = Math.min(1, (i * separacionCostaneras) / largoFaldon);

        for (let lado = 0; lado < aguas; lado++) {
            const [desde, hasta] = lado === 0
                ? [perfil[0], [cumbreY, zCumbre]]
                : [perfil[perfil.length - 1], [cumbreY, zCumbre]];

            costaneras.push([
                desde[0] + (hasta[0] - desde[0]) * t,
                desde[1] + (hasta[1] - desde[1]) * t,
            ]);
        }
    }

    return (
        <g>
            {/* Costaneras y cumbrera: corren a lo largo, sobre las cerchas. */}
            {costaneras.map(([y, z], i) => (
                <line
                    key={`c${i}`}
                    x1={proyectar(0, y, z).x} y1={proyectar(0, y, z).y}
                    x2={proyectar(largo, y, z).x} y2={proyectar(largo, y, z).y}
                    stroke="#b98d52" strokeWidth={trazo * 0.7} strokeLinecap="round"
                />
            ))}

            {posiciones.map((x) => {
                const p = (y, z) => punto(x, y, z);
                const medio = (a, b) => [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
                const cumbre = [cumbreY, zCumbre];

                return (
                    <g key={x} stroke="#8a6134" strokeWidth={trazo * 1.1} fill="none" strokeLinecap="round">
                        {/* Pares: el perfil completo del faldón. */}
                        <polyline points={perfil.map(([y, z]) => p(y, z)).join(' ')} />

                        {/* Tirante: de muro a muro, a la altura del apoyo. */}
                        <polyline points={`${p(0, altoMuro)} ${p(ancho, altoMuro)}`} />

                        {/* Pendolón: del tirante al caballete. */}
                        <polyline points={`${p(cumbreY, altoMuro)} ${p(cumbre[0], cumbre[1])}`} />

                        {/* Diagonales: del pie del pendolón a la mitad de cada par. */}
                        {[perfil[0], perfil[perfil.length - 1]].slice(0, aguas).map(([y, z], i) => {
                            const [my, mz] = medio([y, z], cumbre);
                            return (
                                <polyline key={i} points={`${p(cumbreY, altoMuro)} ${p(my, mz)}`} strokeWidth={trazo * 0.8} />
                            );
                        })}
                    </g>
                );
            })}
        </g>
    );
}

export default function PlanoIsometrico({
    largoMm = 6000,
    anchoMm = 4000,
    altoMm = 2400,
    separacionMm = 400,
    espesorPiezaMm = 41,
    vanosPorCara = {},
    filasCadenetas = 0,
    techo = null,
}) {
    const muros = murosDe(largoMm, anchoMm);

    // Todo el grosor de linea se deriva de la dimension mayor. El viewBox esta en
    // milimetros, asi que un numero fijo daria trazos invisibles en una planta
    // grande y trazos gruesos como muros en una chica.
    const trazo = Math.max(largoMm, anchoMm, altoMm) / 110;

    // El recuadro se calcula desde las esquinas proyectadas y no a ojo: así el
    // dibujo queda encuadrado para cualquier planta, no solo para la de prueba.
    // El techo asoma por encima y por los costados, así que sus extremos también
    // cuentan para encuadrar: sin ellos el alero quedaría cortado.
    const alto = altoMm + (techo?.alturaCumbrera ?? 0);
    const vuelo = techo?.alero ?? 0;

    const esquinas = [
        [0, 0, 0], [largoMm, 0, 0], [largoMm, anchoMm, 0], [0, anchoMm, 0],
        [-vuelo, -vuelo, alto], [largoMm + vuelo, -vuelo, alto],
        [largoMm + vuelo, anchoMm + vuelo, alto], [-vuelo, anchoMm + vuelo, alto],
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
            {muros.map((muro, i) => (
                <Muro
                    key={muro.clave}
                    muro={muro}
                    alto={altoMm}
                    separacion={separacionMm}
                    trazo={trazo}
                    espesor={espesorPiezaMm}
                    vanos={vanosPorCara[i] ?? []}
                    filasCadenetas={filasCadenetas}
                    // Los dos de atrás van atenuados y se pintan primero, para que
                    // los de adelante los tapen: es lo único que da volumen sin
                    // calcular oclusión de verdad.
                    tenue={i < 2}
                />
            ))}

            {techo && (
                <Techumbre
                    largo={largoMm}
                    ancho={anchoMm}
                    altoMuro={altoMm}
                    techo={techo}
                    trazo={trazo}
                />
            )}

            <Cota desde={[0, anchoMm, 0]} hasta={[largoMm, anchoMm, 0]} texto={metros(largoMm)} trazo={trazo} desplazamiento={[0, trazo * 11]} />
            <Cota desde={[largoMm, 0, 0]} hasta={[largoMm, anchoMm, 0]} texto={metros(anchoMm)} trazo={trazo} desplazamiento={[trazo * 11, trazo * 6]} />
            <Cota desde={[largoMm, anchoMm, 0]} hasta={[largoMm, anchoMm, altoMm]} texto={metros(altoMm)} trazo={trazo} desplazamiento={[trazo * 13, 0]} />
        </svg>
    );
}
