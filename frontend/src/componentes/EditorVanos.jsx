import CampoMedida from './CampoMedida';
import Selector from './Selector';

/**
 * Editor de puertas y ventanas, cara por cara.
 *
 * El aviso de encaje es la parte que importa. Un vano cuyas jambas no caen sobre
 * la trama de pies derechos deja un tramo residual angosto al lado, donde el
 * canto del revestimiento se queda sin apoyo y hay que agregar una pieza a
 * medida en obra. Verlo al escribir la medida evita descubrirlo con el muro
 * armado.
 *
 * Se avisa, no se impone: una puerta viene del fabricante con su medida y no se
 * estira. Lo que sí se puede correr es la separación entre pies derechos.
 */

const MM_POR_UNIDAD = { m: 1000, cm: 10, mm: 1, ft: 304.8, in: 25.4 };

const desdeMm = (mm, unidad) => Number((mm / MM_POR_UNIDAD[unidad]).toFixed(unidad === 'mm' ? 0 : 3));

function Encaje({ diagnostico, unidad, onAjustar }) {
    if (!diagnostico) return null;

    if (diagnostico.calza_con_la_trama) {
        return (
            <span className="encaje encaje--calza">
                <span aria-hidden="true">✓</span>
                Calza con los pies derechos
            </span>
        );
    }

    const sugerido = diagnostico.ancho_sugerido_mm;

    return (
        <span className="encaje encaje--no-calza">
            <span aria-hidden="true">▲</span>
            <span>
                Ocupa {diagnostico.tramos_que_ocupa.toFixed(2)} tramos: queda un trozo
                suelto al lado.
                {sugerido != null && (
                    <>
                        {' '}
                        <button
                            type="button"
                            className="boton-chico"
                            onClick={() => onAjustar(desdeMm(sugerido, unidad))}
                        >
                            Ajustar a {desdeMm(sugerido, unidad)} {unidad}
                        </button>
                    </>
                )}
            </span>
        </span>
    );
}

/**
 * Corre el vano de a un pie derecho.
 *
 * Con botones y no con un campo numérico a propósito: el vano tiene que arrancar
 * sobre un pie derecho igual, y escribir la posición con una regla permitiría
 * dejarlo a 37 cm del anterior, que es justo el error que la trama evita. Además
 * se ve moverse en el dibujo mientras se aprieta.
 */
function Ubicacion({ vano, diagnostico, onCambiar }) {
    const ultimo = diagnostico?.ultimo_tramo_posible ?? 1;
    const actual = vano.desdeTramo;

    if (actual == null) {
        return (
            <div className="ubicacion">
                <span className="ubicacion__valor" style={{ fontFamily: 'var(--fuente-texto)' }}>
                    Repartido automáticamente
                </span>
                <button
                    type="button"
                    className="boton-chico"
                    onClick={() => onCambiar({ desdeTramo: 1 })}
                >
                    Ubicar
                </button>
            </div>
        );
    }

    return (
        <div className="ubicacion">
            <button
                type="button"
                className="flecha"
                onClick={() => onCambiar({ desdeTramo: Math.max(1, actual - 1) })}
                disabled={actual <= 1}
                aria-label="Correr un pie derecho hacia la izquierda"
            >
                ◀
            </button>

            <span className="ubicacion__valor">
                Pie derecho {actual}
                <span className="visualmente-oculto"> de {ultimo} posibles</span>
            </span>

            <button
                type="button"
                className="flecha"
                onClick={() => onCambiar({ desdeTramo: Math.min(ultimo, actual + 1) })}
                disabled={actual >= ultimo}
                aria-label="Correr un pie derecho hacia la derecha"
            >
                ▶
            </button>

            <button
                type="button"
                className="boton-chico"
                onClick={() => onCambiar({ desdeTramo: null })}
                title="Volver a repartirlo automáticamente"
            >
                Auto
            </button>
        </div>
    );
}

function Vano({ vano, diagnostico, onCambiar, onQuitar }) {
    const esVentana = vano.tipo === 'ventana';

    return (
        <div className="vano">
            <div className="vano__fila">
                <Selector
                    rotulo="Tipo"
                    valor={vano.tipo}
                    onCambiar={(v) => onCambiar({
                        tipo: v,
                        // Una puerta nace del piso por definición: el backend
                        // rechaza una con antepecho, así que se limpia al cambiar.
                        ...(v === 'puerta' ? { antepecho: '0' } : {}),
                    })}
                    opciones={[
                        { valor: 'ventana', rotulo: 'Ventana' },
                        { valor: 'puerta', rotulo: 'Puerta' },
                    ]}
                />

                <Selector
                    rotulo="Cantidad"
                    valor={vano.cantidad}
                    onCambiar={(v) => onCambiar({
                        cantidad: Number(v),
                        // Varios vanos no pueden compartir la misma posición: al
                        // pedir más de uno vuelven a repartirse solos.
                        ...(Number(v) > 1 ? { desdeTramo: null } : {}),
                    })}
                    opciones={[1, 2, 3, 4, 5, 6, 8, 10].map((n) => ({
                        valor: n,
                        rotulo: n === 1 ? '1 (una)' : `${n} iguales`,
                    }))}
                    nota={vano.cantidad > 1 ? 'Con más de uno se reparten solos.' : null}
                />
            </div>

            <div className="vano__fila">
                <CampoMedida
                    rotulo="Ancho"
                    valor={vano.ancho}
                    unidad={vano.unidad}
                    onValor={(v) => onCambiar({ ancho: v })}
                    onUnidad={(u) => onCambiar({ unidad: u })}
                />
                <CampoMedida
                    rotulo="Alto"
                    valor={vano.alto}
                    unidad={vano.unidad}
                    onValor={(v) => onCambiar({ alto: v })}
                    onUnidad={(u) => onCambiar({ unidad: u })}
                    mostrarUnidad={false}
                />
            </div>

            {esVentana && (
                <div style={{ marginBottom: 8 }}>
                    <CampoMedida
                        rotulo="Antepecho"
                        valor={vano.antepecho}
                        unidad={vano.unidad}
                        onValor={(v) => onCambiar({ antepecho: v })}
                        onUnidad={(u) => onCambiar({ unidad: u })}
                        mostrarUnidad={false}
                        nota="Del piso al borde de abajo de la ventana."
                    />
                </div>
            )}

            <span className="campo__rotulo" style={{ display: 'block', marginBottom: 4 }}>
                Ubicación
            </span>
            <Ubicacion vano={vano} diagnostico={diagnostico} onCambiar={onCambiar} />

            <div className="vano__pie">
                <Encaje
                    diagnostico={diagnostico}
                    unidad={vano.unidad}
                    onAjustar={(ancho) => onCambiar({ ancho: String(ancho) })}
                />

                <button type="button" className="boton-chico boton-chico--quitar" onClick={onQuitar}>
                    Quitar
                </button>
            </div>
        </div>
    );
}

export default function EditorVanos({ caras, vanos, diagnostico, onVanos }) {
    const agregar = (indiceCara, tipo) => {
        const nuevo = tipo === 'puerta'
            ? { tipo: 'puerta', ancho: '0.9', alto: '2', antepecho: '0', cantidad: 1, unidad: 'm', desdeTramo: null }
            : { tipo: 'ventana', ancho: '1.2', alto: '1', antepecho: '0.9', cantidad: 1, unidad: 'm', desdeTramo: null };

        onVanos(indiceCara, [...(vanos[indiceCara] ?? []), nuevo]);
    };

    const cambiar = (indiceCara, indiceVano, cambio) => {
        onVanos(
            indiceCara,
            (vanos[indiceCara] ?? []).map((v, i) => (i === indiceVano ? { ...v, ...cambio } : v)),
        );
    };

    const quitar = (indiceCara, indiceVano) => {
        onVanos(indiceCara, (vanos[indiceCara] ?? []).filter((_, i) => i !== indiceVano));
    };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            {caras.map((cara, i) => (
                <section key={cara.nombre} className="cara">
                    <h3 className="cara__titulo">
                        {cara.nombre}
                        <span className="cara__medida">
                            {cara.largo} × {cara.alto} {cara.unidad}
                        </span>
                    </h3>

                    {(vanos[i] ?? []).map((vano, j) => (
                        <Vano
                            key={j}
                            vano={vano}
                            diagnostico={diagnostico?.[i]?.vanos?.[j]}
                            onCambiar={(cambio) => cambiar(i, j, cambio)}
                            onQuitar={() => quitar(i, j)}
                        />
                    ))}

                    {(vanos[i] ?? []).length === 0 && (
                        <p className="campo__nota" style={{ margin: '0 0 8px' }}>
                            Sin puertas ni ventanas: muro ciego.
                        </p>
                    )}

                    <div style={{ display: 'flex', gap: 6 }}>
                        <button type="button" className="boton-chico" onClick={() => agregar(i, 'ventana')}>
                            + Ventana
                        </button>
                        <button type="button" className="boton-chico" onClick={() => agregar(i, 'puerta')}>
                            + Puerta
                        </button>
                    </div>
                </section>
            ))}
        </div>
    );
}
