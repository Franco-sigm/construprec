import { useMemo, useState } from 'react';
import BarraHerramientas from './componentes/BarraHerramientas';
import BarraSuperior from './componentes/BarraSuperior';
import Boton from './componentes/Boton';
import ListaMateriales from './componentes/ListaMateriales';
import Panel from './componentes/Panel';
import PlanoIsometrico from './componentes/PlanoIsometrico';

/**
 * Datos de muestra mientras no existe la API.
 *
 * No son inventados: son exactamente lo que devuelve CalculoProyectoService para
 * una planta de 6 x 4 con una puerta y dos ventanas. Usar los números reales
 * evita que la pantalla se diseñe contra un caso imposible y después no calce.
 */
const PROYECTO = {
    nombre: 'Ampliación living',
    largoMm: 6000,
    anchoMm: 4000,
    altoMm: 2400,
    separacionMm: 400,
};

const MATERIALES = [
    { clave: 'madera', nombre: 'Pino 2x3 seco cepillado 3,20 m', unidad_venta: 'tiras', cantidad_comprar: 76 },
    { clave: 'capa_1', nombre: 'OSB estructural 11,1 mm', unidad_venta: 'planchas', cantidad_comprar: 16 },
    { clave: 'capa_2', nombre: 'Membrana hidrófuga', unidad_venta: 'rollos', cantidad_comprar: 1 },
    { clave: 'capa_3', nombre: 'Siding fibrocemento', unidad_venta: 'planchas', cantidad_comprar: 16 },
    { clave: 'capa_4', nombre: 'Lana de vidrio 50 mm', unidad_venta: 'rollos', cantidad_comprar: 4 },
    { clave: 'capa_5', nombre: 'Yeso-cartón 8 mm', unidad_venta: 'planchas', cantidad_comprar: 17 },
];

const ETAPAS = [
    { clave: 'muros', rotulo: 'Muros', listo: true },
    { clave: 'entrepiso', rotulo: 'Entrepiso', listo: false },
    { clave: 'techo', rotulo: 'Techo', listo: false },
    { clave: 'terminaciones', rotulo: 'Terminaciones', listo: false },
];

export default function App() {
    const [seccion, setSeccion] = useState('materiales');
    const [precios, setPrecios] = useState({});

    // El total se recalcula al escribir, sin botón de por medio: ver el número
    // moverse mientras se cotiza es más útil que tener que pedirlo.
    const total = useMemo(
        () => MATERIALES.reduce(
            (suma, m) => suma + (Number(precios[m.clave]) || 0) * m.cantidad_comprar,
            0,
        ),
        [precios],
    );

    const faltantes = MATERIALES.filter((m) => !precios[m.clave]).length;

    return (
        <div style={{ display: 'flex', flexDirection: 'column', height: '100dvh', overflow: 'hidden' }}>
            <BarraSuperior titulo="Presupuesto" fecha="2026-09-06" onVolver={() => {}} />

            <main
                className="plano"
                style={{
                    flex: 1,
                    minHeight: 0,
                    display: 'grid',
                    gridTemplateColumns: 'minmax(300px, 26rem) 1fr',
                    gap: 16,
                    padding: 16,
                    overflow: 'auto',
                }}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minHeight: 0 }}>
                    <Panel veta="veta-oscura">
                        <div className="cartel-total veta" style={{ border: '2px solid var(--madera-borde)', borderRadius: 'var(--radio-chico)' }}>
                            <div className="cartel-total__rotulo">Presupuesto global</div>
                            <div className="cartel-total__monto">
                                ${new Intl.NumberFormat('es-CL').format(Math.round(total))}
                            </div>
                        </div>

                        <ol
                            style={{
                                display: 'grid',
                                gridTemplateColumns: `repeat(${ETAPAS.length}, 1fr)`,
                                gap: 3,
                                listStyle: 'none',
                                margin: '12px 0 0',
                                padding: 0,
                            }}
                        >
                            {ETAPAS.map((etapa) => (
                                <li
                                    key={etapa.clave}
                                    className="veta titulo"
                                    style={{
                                        padding: '7px 4px',
                                        fontSize: '0.72rem',
                                        textAlign: 'center',
                                        border: '2px solid var(--madera-borde)',
                                        borderRadius: 3,
                                        opacity: etapa.listo ? 1 : 0.62,
                                    }}
                                >
                                    {etapa.rotulo}
                                    <div aria-hidden="true" style={{ fontSize: '0.95rem', lineHeight: 1 }}>
                                        {etapa.listo ? '✓' : '—'}
                                    </div>
                                    <span className="visualmente-oculto">
                                        {etapa.listo ? 'completada' : 'pendiente'}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </Panel>

                    <ListaMateriales
                        titulo="Muros"
                        materiales={MATERIALES}
                        conPrecios={seccion === 'materiales'}
                        precios={precios}
                        onPrecio={(clave, valor) => setPrecios((p) => ({ ...p, [clave]: valor }))}
                    />

                    <Boton principal disabled={faltantes > 0}>
                        {faltantes > 0 ? `Faltan ${faltantes} precios` : 'Generar informe'}
                    </Boton>
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minHeight: 0 }}>
                    <Panel
                        veta=""
                        style={{
                            flex: 1,
                            minHeight: 320,
                            background: 'transparent',
                            border: '3px solid var(--madera-borde)',
                            boxShadow: 'none',
                            padding: 8,
                        }}
                    >
                        <PlanoIsometrico {...PROYECTO} />
                    </Panel>

                    <div style={{ display: 'flex', gap: 12 }}>
                        <Boton>Añadir etapa</Boton>
                        <Boton>Editar medidas</Boton>
                    </div>
                </div>
            </main>

            <div style={{ flexShrink: 0 }}>
                <BarraHerramientas activa={seccion} onCambiar={setSeccion} />
            </div>
        </div>
    );
}
