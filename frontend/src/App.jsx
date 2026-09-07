import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { calcular, obtenerCatalogo } from './api';
import BarraHerramientas from './componentes/BarraHerramientas';
import BarraSuperior from './componentes/BarraSuperior';
import Boton from './componentes/Boton';
import CampoMedida from './componentes/CampoMedida';
import ListaMateriales from './componentes/ListaMateriales';
import Panel from './componentes/Panel';
import PanelConfiguracion from './componentes/PanelConfiguracion';
import PlanoIsometrico from './componentes/PlanoIsometrico';

const HOY = new Date().toISOString().slice(0, 10);

const SECCIONES = ['proyecto', 'etapas', 'materiales'];

/**
 * La sección activa vive en el hash de la URL.
 *
 * Así se puede enlazar directo a un paso, el botón atrás del navegador funciona
 * como se espera, y recargar no devuelve al principio. Es gratis comparado con
 * montar un enrutador entero para tres pestañas.
 */
function seccionDelHash() {
    const hash = window.location.hash.replace('#', '');

    return SECCIONES.includes(hash) ? hash : 'proyecto';
}

/** Planta rectangular de arranque. Se edita en la pestaña Proyecto. */
const PLANTA_INICIAL = { largo: '6', ancho: '4', alto: '2.4', unidad: 'm' };

const CONFIG_INICIAL = {
    escuadriaId: null,
    largoComercialMm: 3200,
    separacion: '0.4',
    separacionUnidad: 'm',
    filasCadenetas: 1,
    solerasSuperiores: 1,
    mermaPct: '5',
};

/**
 * Vanos de arranque, para que la pantalla muestre algo real desde el principio.
 * El editor de vanos por cara es el paso siguiente.
 */
const VANOS = [
    { cara: 0, tipo: 'puerta', ancho: 0.9, alto: 2.0 },
    { cara: 0, tipo: 'ventana', ancho: 1.2, alto: 1.0, antepecho: 0.9 },
    { cara: 1, tipo: 'ventana', ancho: 1.0, alto: 1.0, antepecho: 0.9 },
];

/** Las cuatro caras que salen de una planta rectangular. */
function carasDe(planta) {
    const lados = [planta.largo, planta.ancho, planta.largo, planta.ancho];

    return lados.map((largo, i) => ({
        nombre: `Cara ${i + 1}`,
        largo: Number(largo) || 0,
        alto: Number(planta.alto) || 0,
        unidad: planta.unidad,
        vanos: VANOS.filter((v) => v.cara === i).map(({ cara: _cara, ...vano }) => vano),
    }));
}

export default function App() {
    const [seccion, setSeccion] = useState(seccionDelHash);
    const [catalogo, setCatalogo] = useState(null);
    const [planta, setPlanta] = useState(PLANTA_INICIAL);
    const [config, setConfig] = useState(CONFIG_INICIAL);
    const [capas, setCapas] = useState({});
    const [resultado, setResultado] = useState(null);
    const [precios, setPrecios] = useState({});
    const [error, setError] = useState(null);
    const [calculando, setCalculando] = useState(false);

    // Mantiene sincronizado el hash con la pestaña, en los dos sentidos.
    useEffect(() => {
        const alCambiarHash = () => setSeccion(seccionDelHash());

        window.addEventListener('hashchange', alCambiarHash);
        return () => window.removeEventListener('hashchange', alCambiarHash);
    }, []);

    const cambiarSeccion = useCallback((nueva) => {
        window.location.hash = nueva;
        setSeccion(nueva);
    }, []);

    // --- catálogo, una sola vez ---
    useEffect(() => {
        obtenerCatalogo()
            .then((datos) => {
                setCatalogo(datos);

                // Arranca con la primera escuadría y una capa de cada tipo, para que
                // haya algo que mirar antes de tocar nada.
                const escuadria = datos.escuadrias[0];
                setConfig((c) => ({
                    ...c,
                    escuadriaId: escuadria?.id ?? null,
                    largoComercialMm: escuadria?.largos_comerciales_mm?.includes(3200)
                        ? 3200
                        : escuadria?.largos_comerciales_mm?.[0],
                }));
                setCapas(Object.fromEntries(
                    Object.entries(datos.productos).map(([tipo, ps]) => [tipo, ps[0]?.id ?? null]),
                ));
            })
            .catch((e) => setError(e.message));
    }, []);

    const cuerpo = useMemo(() => {
        if (!config.escuadriaId) return null;

        return {
            caras: carasDe(planta),
            tabiqueria: {
                escuadria_id: Number(config.escuadriaId),
                largo_comercial_mm: Number(config.largoComercialMm),
                separacion: Number(config.separacion) || 0,
                separacion_unidad: config.separacionUnidad,
                filas_cadenetas: Number(config.filasCadenetas),
                soleras_superiores: Number(config.solerasSuperiores),
                merma_pct: Number(config.mermaPct) || 0,
            },
            capas: Object.values(capas)
                .filter(Boolean)
                .map((id) => ({ producto_capa_id: Number(id), aplicacion: 'exterior' })),
        };
    }, [planta, config, capas]);

    // --- recálculo, con freno ---
    // Se espera un momento antes de llamar: sin eso, escribir "2.40" dispara
    // cuatro peticiones y la última en volver puede no ser la última pedida.
    const ultimaPeticion = useRef(0);

    const recalcular = useCallback(async (payload) => {
        const marca = ++ultimaPeticion.current;
        setCalculando(true);

        try {
            const datos = await calcular(payload);

            // Descarta respuestas de peticiones ya superadas: llegan fuera de
            // orden y pintarían un resultado viejo sobre uno nuevo.
            if (marca === ultimaPeticion.current) {
                setResultado(datos);
                setError(null);
            }
        } catch (e) {
            if (marca === ultimaPeticion.current) {
                setError(e.message);
                setResultado(null);
            }
        } finally {
            if (marca === ultimaPeticion.current) setCalculando(false);
        }
    }, []);

    useEffect(() => {
        if (!cuerpo) return undefined;

        const id = setTimeout(() => recalcular(cuerpo), 350);
        return () => clearTimeout(id);
    }, [cuerpo, recalcular]);

    // Se memoriza la lista y no sólo el total: `?? []` crea un arreglo nuevo en
    // cada render, así que sin esto el useMemo del total no memorizaría nada.
    const materiales = useMemo(() => resultado?.materiales ?? [], [resultado]);

    const total = useMemo(
        () => materiales.reduce((s, m) => s + (Number(precios[m.clave]) || 0) * m.cantidad_comprar, 0),
        [materiales, precios],
    );

    const faltantes = materiales.filter((m) => !precios[m.clave]).length;
    const enMm = (valor) => (Number(valor) || 0) * { m: 1000, cm: 10, mm: 1, ft: 304.8, in: 25.4 }[planta.unidad];

    if (error && !catalogo) {
        return (
            <div className="plano" style={{ minHeight: '100dvh', display: 'grid', placeItems: 'center', padding: 24 }}>
                <Panel style={{ maxWidth: 520 }}>
                    <h1 className="titulo" style={{ marginTop: 0 }}>No hay conexión con la API</h1>
                    <p className="aviso">{error}</p>
                    <p style={{ fontSize: '0.9rem' }}>
                        Levanta el backend con <code>php artisan serve</code> desde <code>backend/</code>.
                    </p>
                </Panel>
            </div>
        );
    }

    return (
        <div style={{ display: 'flex', flexDirection: 'column', height: '100dvh', overflow: 'hidden' }}>
            <BarraSuperior titulo="Presupuesto" fecha={HOY} onVolver={() => {}} />

            <main
                className="plano"
                style={{
                    flex: 1,
                    minHeight: 0,
                    display: 'grid',
                    gridTemplateColumns: 'minmax(320px, 27rem) 1fr',
                    gap: 16,
                    padding: 16,
                    overflow: 'hidden',
                }}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16, overflow: 'auto', paddingRight: 4 }}>
                    <Panel veta="veta-oscura">
                        <div
                            className="cartel-total veta"
                            style={{ border: '2px solid var(--madera-borde)', borderRadius: 'var(--radio-chico)' }}
                        >
                            <div className="cartel-total__rotulo">Presupuesto global</div>
                            <div className="cartel-total__monto">
                                ${new Intl.NumberFormat('es-CL').format(Math.round(total))}
                            </div>
                        </div>
                    </Panel>

                    {error && <p className="aviso">{error}</p>}

                    {seccion === 'proyecto' && (
                        <Panel style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            <h2 className="titulo" style={{ margin: 0, fontSize: '1.05rem' }}>Planta</h2>

                            {['largo', 'ancho', 'alto'].map((campo) => (
                                <CampoMedida
                                    key={campo}
                                    rotulo={campo}
                                    valor={planta[campo]}
                                    unidad={planta.unidad}
                                    onValor={(v) => setPlanta((p) => ({ ...p, [campo]: v }))}
                                    onUnidad={(u) => setPlanta((p) => ({ ...p, unidad: u }))}
                                />
                            ))}

                            <p className="campo__nota" style={{ margin: 0 }}>
                                De estas tres medidas salen las cuatro caras. Cada una queda
                                editable por separado más adelante.
                            </p>
                        </Panel>
                    )}

                    {seccion === 'etapas' && catalogo && (
                        <PanelConfiguracion
                            catalogo={catalogo}
                            config={config}
                            onConfig={(cambio) => setConfig((c) => ({ ...c, ...cambio }))}
                            capas={capas}
                            onCapa={(tipo, id) => setCapas((c) => ({ ...c, [tipo]: id }))}
                        />
                    )}

                    {seccion === 'materiales' && (
                        <>
                            <ListaMateriales
                                titulo={calculando ? 'Calculando…' : 'Muros'}
                                materiales={materiales}
                                conPrecios
                                precios={precios}
                                onPrecio={(clave, valor) => setPrecios((p) => ({ ...p, [clave]: valor }))}
                            />
                            <Boton principal disabled={faltantes > 0 || materiales.length === 0}>
                                {faltantes > 0 ? `Faltan ${faltantes} precios` : 'Generar informe'}
                            </Boton>
                        </>
                    )}
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minHeight: 0 }}>
                    <Panel
                        veta=""
                        style={{
                            flex: 1,
                            minHeight: 300,
                            background: 'transparent',
                            border: '3px solid var(--madera-borde)',
                            boxShadow: 'none',
                            padding: 8,
                        }}
                    >
                        <PlanoIsometrico
                            largoMm={enMm(planta.largo)}
                            anchoMm={enMm(planta.ancho)}
                            altoMm={enMm(planta.alto)}
                            separacionMm={
                                (Number(config.separacion) || 0.4)
                                * { m: 1000, cm: 10, mm: 1, ft: 304.8, in: 25.4 }[config.separacionUnidad]
                            }
                        />
                    </Panel>

                    {resultado && (
                        <Panel className="veta" style={{ display: 'flex', flexWrap: 'wrap', gap: 18, justifyContent: 'space-around' }}>
                            {[
                                ['Superficie neta', `${resultado.obra.superficie_neta_m2.toFixed(2)} m²`],
                                ['Cavidad real', `${resultado.obra.cavidad_m2.toFixed(2)} m²`],
                                ['Piezas a cortar', resultado.obra.piezas],
                                ['Tiras a comprar', resultado.corte.tiras_a_comprar],
                                ['Recorte', `${resultado.corte.desperdicio_m.toFixed(1)} m`],
                            ].map(([rotulo, valor]) => (
                                <div key={rotulo} style={{ textAlign: 'center' }}>
                                    <div className="campo__rotulo">{rotulo}</div>
                                    <div className="titulo" style={{ fontSize: '1.3rem' }}>{valor}</div>
                                </div>
                            ))}
                        </Panel>
                    )}
                </div>
            </main>

            <div style={{ flexShrink: 0 }}>
                <BarraHerramientas activa={seccion} onCambiar={cambiarSeccion} />
            </div>
        </div>
    );
}
