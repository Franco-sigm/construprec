import CampoMedida from './CampoMedida';
import Panel from './Panel';
import Selector from './Selector';

/**
 * Configuración de la tabiquería y elección de productos.
 *
 * Cada cambio dispara un recálculo contra el backend, así que el usuario ve al
 * instante qué le cuesta separar los pies derechos a 60 en vez de 40, o poner
 * terciado ranurado en vez de yeso-cartón. Ese ida y vuelta es lo que convierte
 * la aplicación en una herramienta de decisión y no en una calculadora.
 */

const TIPOS_CAPA = [
    { clave: 'arriostramiento', rotulo: 'Arriostramiento' },
    { clave: 'membrana', rotulo: 'Membrana hidrófuga' },
    { clave: 'rev_exterior', rotulo: 'Revestimiento exterior' },
    { clave: 'aislante', rotulo: 'Aislante térmico' },
    { clave: 'rev_interior', rotulo: 'Revestimiento interior' },
];

function Bloque({ titulo, children }) {
    return (
        <section style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            <h3
                className="titulo veta"
                style={{
                    margin: 0,
                    padding: '7px 12px',
                    fontSize: '0.95rem',
                    border: '2px solid var(--madera-borde)',
                    borderRadius: 'var(--radio-chico)',
                    boxShadow: 'inset 0 2px 0 rgba(255,240,210,.45)',
                }}
            >
                {titulo}
            </h3>
            {children}
        </section>
    );
}

export default function PanelConfiguracion({ catalogo, config, onConfig, capas, onCapa, perdida = null }) {
    const escuadria = catalogo.escuadrias.find((e) => String(e.id) === String(config.escuadriaId));

    // Un producto de siding rinde menos que su ancho porque va montado sobre el
    // anterior. Explicarlo donde se elige evita la pregunta de por qué hacen
    // falta 83 tablas y no 16 planchas.
    const notaProducto = (producto) => {
        if (!producto) return null;

        const rinde = `rinde ${producto.rendimiento_m2.toFixed(4)} m² por ${producto.unidad_venta}`;

        return producto.traslape_mm > 0
            ? `${rinde} — de sus ${producto.ancho_mm} mm sólo quedan ${producto.ancho_util_mm} a la vista`
            : rinde;
    };

    return (
        <Panel style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <Bloque titulo="Tabiquería">
                <Selector
                    rotulo="Escuadría"
                    valor={config.escuadriaId}
                    onCambiar={(v) => onConfig({ escuadriaId: v })}
                    opciones={catalogo.escuadrias.map((e) => ({
                        valor: e.id,
                        rotulo: `${e.descripcion} · ${e.ancho_real_mm} × ${e.alto_real_mm} mm`,
                    }))}
                    nota={escuadria && `Medida real ${escuadria.ancho_real_mm} × ${escuadria.alto_real_mm} mm`}
                />

                <Selector
                    rotulo="Largo comercial"
                    valor={config.largoComercialMm}
                    onCambiar={(v) => onConfig({ largoComercialMm: v })}
                    opciones={(escuadria?.largos_comerciales_mm ?? []).map((l) => ({
                        valor: l,
                        rotulo: `${(l / 1000).toFixed(2)} m`,
                    }))}
                    nota="El despiece elige cómo cortar; acá se elige qué tira comprar."
                />

                <CampoMedida
                    rotulo="Separación entre pies derechos"
                    valor={config.separacion}
                    unidad={config.separacionUnidad}
                    onValor={(v) => onConfig({ separacion: v })}
                    onUnidad={(u) => onConfig({ separacionUnidad: u })}
                    nota="Se mide entre ejes. En pulgadas se suele usar 16 o 24."
                />

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                    <Selector
                        rotulo="Filas de cadenetas"
                        valor={config.filasCadenetas}
                        onCambiar={(v) => onConfig({ filasCadenetas: v })}
                        opciones={[
                            { valor: 0, rotulo: 'Sin cadenetas' },
                            { valor: 1, rotulo: '1 fila' },
                            { valor: 2, rotulo: '2 filas' },
                            { valor: 3, rotulo: '3 filas' },
                        ]}
                    />

                    <Selector
                        rotulo="Soleras superiores"
                        valor={config.solerasSuperiores}
                        onCambiar={(v) => onConfig({ solerasSuperiores: v })}
                        opciones={[
                            { valor: 1, rotulo: 'Simple' },
                            { valor: 2, rotulo: 'Doble' },
                        ]}
                    />
                </div>

                <p className="campo__nota" style={{ margin: 0 }}>
                    Las cadenetas salen del recorte que dejan los cortes largos: suman
                    metros de madera pero no suelen costar tiras.
                </p>

                <Selector
                    rotulo="Piezas por esquina"
                    valor={config.piezasPorEsquina}
                    onCambiar={(v) => onConfig({ piezasPorEsquina: v })}
                    opciones={[
                        { valor: 2, rotulo: '2 — sin refuerzo' },
                        { valor: 3, rotulo: '3 — poste simple' },
                        { valor: 4, rotulo: '4 — poste doble' },
                    ]}
                    nota="En el encuentro ya hay dos pies derechos, uno por cada muro, pero ninguno deja cara libre hacia adentro para clavar el canto de la plancha. Las que se elijan de más se agregan."
                />

                <CampoMedida
                    rotulo="Descarte por defectos"
                    valor={config.mermaPct}
                    unidad="%"
                    onValor={(v) => onConfig({ mermaPct: v })}
                    onUnidad={() => {}}
                    unidades={[{ valor: '%', rotulo: '%' }]}
                    paso="1"
                    nota="Piezas con nudos, torcidas o rajadas que hay que apartar. Depende del grado de la madera y de la barraca, así que es lo único que no se puede calcular."
                />

                {perdida !== null && (
                    <p className="campo__nota" style={{ margin: 0 }}>
                        El recorte del despiece y lo que se lleva la sierra ya van
                        calculados aparte: <strong>{perdida.toFixed(1)} %</strong> del
                        material en este proyecto. No hay que sumarlo acá.
                    </p>
                )}
            </Bloque>

            <Bloque titulo="Capas">
                {TIPOS_CAPA.map(({ clave, rotulo }) => {
                    const opciones = catalogo.productos[clave] ?? [];
                    const elegido = opciones.find((p) => String(p.id) === String(capas[clave]));

                    return (
                        <Selector
                            key={clave}
                            rotulo={rotulo}
                            valor={capas[clave]}
                            permiteVacio
                            onCambiar={(v) => onCapa(clave, v)}
                            opciones={opciones.map((p) => ({ valor: p.id, rotulo: p.nombre }))}
                            nota={notaProducto(elegido)}
                        />
                    );
                })}
            </Bloque>
        </Panel>
    );
}
