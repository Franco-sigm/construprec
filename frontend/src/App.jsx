import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    abrirProyecto,
    actualizarProyecto,
    calcular,
    crearProyecto,
    descargarPdf,
    emitirPresupuesto,
    entrar,
    guardarProyectoAbierto,
    guardarToken,
    leerProyectoAbierto,
    leerToken,
    listarProyectos,
    obtenerCatalogo,
    quienSoy,
    salir,
} from './api';
import BarraHerramientas from './componentes/BarraHerramientas';
import BarraSuperior from './componentes/BarraSuperior';
import Boton from './componentes/Boton';
import CampoMedida from './componentes/CampoMedida';
import Entrada from './componentes/Entrada';
import EditorVanos from './componentes/EditorVanos';
import ListaMateriales from './componentes/ListaMateriales';
import Panel from './componentes/Panel';
import PanelConfiguracion from './componentes/PanelConfiguracion';
import PanelTechumbre from './componentes/PanelTechumbre';
import PlanoIsometrico from './componentes/PlanoIsometrico';
import Presupuesto from './componentes/Presupuesto';

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
    piezasPorEsquina: 3,
};

const MM_POR_UNIDAD = { m: 1000, cm: 10, mm: 1, ft: 304.8, in: 25.4 };

const aMm = (valor, unidad) => Math.round((Number(valor) || 0) * MM_POR_UNIDAD[unidad]);

/**
 * Decimales con que tiene sentido mostrar cada unidad.
 *
 * Es la misma tabla que `Unidad::decimales()` en el backend, y por la misma
 * razón: como todo se guarda en milímetros enteros, mostrar más decimales de los
 * que la unidad puede representar deja al descubierto el redondeo. 8 pies se
 * guardan como 2438 mm y al volver dan 7,99869, que hay que mostrar como 8,00 o
 * el usuario cree que la aplicación le cambió el dato.
 *
 * Está duplicada a los dos lados a sabiendas: son cinco números que no cambian
 * nunca, y la alternativa —pedirle al backend el valor ya formateado en cada
 * campo— agrega plomería a cambio de nada.
 */
const DECIMALES = { mm: 0, cm: 1, m: 3, in: 1, ft: 2 };

/** De milímetros guardados al número que se muestra en el campo. */
function desdeMm(mm, unidad) {
    const valor = (Number(mm) || 0) / MM_POR_UNIDAD[unidad];

    return String(Number(valor.toFixed(DECIMALES[unidad] ?? 2)));
}

/**
 * Traduce un proyecto guardado al estado del formulario.
 *
 * La base guarda milímetros; los campos muestran el número en la unidad que el
 * usuario había elegido. Sin esta vuelta, reabrir un proyecto hecho en pies lo
 * mostraría en milímetros y parecería otro.
 */
function desdeApi(proyecto) {
    const u = proyecto.planta?.unidad_ingreso ?? 'm';
    const t = proyecto.tabiqueria;

    const vanos = {};
    proyecto.caras.forEach((cara, i) => {
        vanos[i] = cara.vanos.map((v) => ({
            tipo: v.tipo,
            ancho: desdeMm(v.ancho_mm, v.unidad),
            alto: desdeMm(v.alto_mm, v.unidad),
            antepecho: desdeMm(v.antepecho_mm, v.unidad),
            cantidad: v.cantidad,
            unidad: v.unidad,
            desdeTramo: v.desde_tramo ?? null,
        }));
    });

    const t2 = proyecto.techumbre;

    return {
        nombre: proyecto.nombre,
        techo: t2 === null || t2 === undefined ? null : {
            aguas: t2.aguas,
            luz: desdeMm(t2.luz_mm, t2.unidad),
            largo: desdeMm(t2.largo_mm, t2.unidad),
            alturaCumbrera: desdeMm(t2.altura_cumbrera_mm, t2.unidad),
            alero: desdeMm(t2.alero_mm, t2.unidad),
            unidad: t2.unidad,
            escuadriaId: t2.escuadria_id,
            largoComercialMm: t2.largo_comercial_mm,
            separacionCerchas: desdeMm(t2.separacion_cerchas_mm, t2.separacion_unidad),
            separacionCostaneras: desdeMm(t2.separacion_costaneras_mm, t2.separacion_unidad),
            separacionUnidad: t2.separacion_unidad,
            mermaPct: String(t2.merma_pct),
            cubiertaId: t2.capas?.[0]?.producto_capa_id ?? null,
        },
        planta: {
            largo: desdeMm(proyecto.planta?.largo_mm, u),
            ancho: desdeMm(proyecto.planta?.ancho_mm, u),
            alto: desdeMm(proyecto.planta?.alto_mm, u),
            unidad: u,
        },
        config: t === null ? null : {
            escuadriaId: t.escuadria_id,
            largoComercialMm: t.largo_comercial_mm,
            separacion: desdeMm(t.separacion_mm, t.separacion_unidad),
            separacionUnidad: t.separacion_unidad,
            filasCadenetas: t.filas_cadenetas,
            solerasSuperiores: t.soleras_superiores,
            mermaPct: String(t.merma_pct),
            piezasPorEsquina: t.piezas_por_esquina ?? 3,
        },
        capas: Object.fromEntries(proyecto.capas.map((c) => [c.tipo, c.producto_capa_id])),
        vanos,
    };
}

/** Techumbre de arranque: dos aguas sobre la planta inicial. */
const TECHO_INICIAL = {
    aguas: 2,
    luz: '4',
    largo: '6',
    alturaCumbrera: '1',
    alero: '0.5',
    unidad: 'm',
    escuadriaId: null,
    largoComercialMm: 4000,
    separacionCerchas: '1',
    separacionCostaneras: '1.1',
    separacionUnidad: 'm',
    mermaPct: '5',
    cubiertaId: null,
};

/** Vanos de arranque, para que la pantalla muestre algo real desde el principio. */
const VANOS_INICIALES = {
    0: [
        { tipo: 'puerta', ancho: '0.9', alto: '2', antepecho: '0', cantidad: 1, unidad: 'm', desdeTramo: 2 },
        { tipo: 'ventana', ancho: '1.2', alto: '1', antepecho: '0.9', cantidad: 1, unidad: 'm', desdeTramo: 8 },
    ],
    1: [
        { tipo: 'ventana', ancho: '1', alto: '1', antepecho: '0.9', cantidad: 1, unidad: 'm', desdeTramo: 4 },
    ],
};

/**
 * Las cuatro caras que salen de una planta rectangular.
 *
 * Los vanos viven aparte y se pegan por índice de cara, así que cambiar el largo
 * de la planta no borra las ventanas que ya se habían ingresado.
 */
function carasDe(planta, vanos) {
    const lados = [planta.largo, planta.ancho, planta.largo, planta.ancho];

    return lados.map((largo, i) => ({
        nombre: `Cara ${i + 1}`,
        largo: largo,
        alto: planta.alto,
        unidad: planta.unidad,
        vanos: vanos[i] ?? [],
    }));
}

/** Convierte al formato que espera la API: números, no cadenas del formulario. */
function paraLaApi(cara) {
    return {
        nombre: cara.nombre,
        largo: Number(cara.largo) || 0,
        alto: Number(cara.alto) || 0,
        unidad: cara.unidad,
        vanos: cara.vanos
            .filter((v) => Number(v.ancho) > 0 && Number(v.alto) > 0)
            .map((v) => ({
                tipo: v.tipo,
                ancho: Number(v.ancho),
                alto: Number(v.alto),
                antepecho: v.tipo === 'puerta' ? 0 : Number(v.antepecho) || 0,
                cantidad: Number(v.cantidad) || 1,
                unidad: v.unidad,
                desde_tramo: v.desdeTramo ?? null,
            })),
    };
}

export default function App() {
    const [seccion, setSeccion] = useState(seccionDelHash);
    // El panel derecho muestra el plano o el presupuesto ya desglosado. Se cambia
    // sin salir de la pantalla, para poder ajustar un precio y ver el documento
    // moverse en el mismo golpe de vista.
    const [vista, setVista] = useState('plano');
    const [usuario, setUsuario] = useState(null);
    const [mostrandoEntrada, setMostrandoEntrada] = useState(false);
    const [proyectoId, setProyectoId] = useState(null);
    const [nombre, setNombre] = useState('Proyecto sin nombre');
    const [mios, setMios] = useState([]);
    const [guardando, setGuardando] = useState(false);
    // Firma de lo último que se guardó. Comparar es más barato y más fiable que
    // un efecto que ponga "sin guardar" en cada tecla.
    const [firmaGuardada, setFirmaGuardada] = useState(null);
    const [emitido, setEmitido] = useState(null);
    const [catalogo, setCatalogo] = useState(null);
    const [planta, setPlanta] = useState(PLANTA_INICIAL);
    const [config, setConfig] = useState(CONFIG_INICIAL);
    const [capas, setCapas] = useState({});
    const [vanos, setVanos] = useState(VANOS_INICIALES);
    const [techo, setTecho] = useState(TECHO_INICIAL);
    const [conTecho, setConTecho] = useState(false);
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

    // Si hay token guardado se comprueba contra la API en vez de darlo por bueno:
    // pudo vencer o haber sido revocado desde otro dispositivo.
    useEffect(() => {
        if (!leerToken()) return;

        quienSoy()
            .then(setUsuario)
            .catch(() => guardarToken(null));
    }, []);

    // La lista de proyectos se refresca cada vez que cambia quién está adentro.
    useEffect(() => {
        if (!usuario) return;

        listarProyectos().then((d) => setMios(d.proyectos)).catch(() => {});
    }, [usuario]);

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
                    Object.entries(datos.productos)
                        // La cubierta va en la techumbre, no entre las capas del muro.
                        .filter(([tipo]) => tipo !== 'cubierta')
                        .map(([tipo, ps]) => [tipo, ps[0]?.id ?? null]),
                ));

                // Para la cercha se propone la escuadría más gruesa disponible: es
                // la que salva más luz y la que menos sorpresas da.
                const gruesa = [...datos.escuadrias].sort(
                    (a, b) => b.alto_real_mm - a.alto_real_mm,
                )[0];

                setTecho((t) => ({
                    ...t,
                    escuadriaId: gruesa?.id ?? null,
                    cubiertaId: datos.productos.cubierta?.[0]?.id ?? null,
                }));
            })
            .catch((e) => setError(e.message));
    }, []);

    const caras = useMemo(() => carasDe(planta, vanos), [planta, vanos]);

    const cuerpo = useMemo(() => {
        if (!config.escuadriaId) return null;

        return {
            caras: caras.map(paraLaApi),
            tabiqueria: {
                escuadria_id: Number(config.escuadriaId),
                largo_comercial_mm: Number(config.largoComercialMm),
                separacion: Number(config.separacion) || 0,
                separacion_unidad: config.separacionUnidad,
                filas_cadenetas: Number(config.filasCadenetas),
                soleras_superiores: Number(config.solerasSuperiores),
                merma_pct: Number(config.mermaPct) || 0,
                piezas_por_esquina: Number(config.piezasPorEsquina),
            },
            capas: Object.values(capas)
                .filter(Boolean)
                .map((id) => ({ producto_capa_id: Number(id), aplicacion: 'exterior' })),

            ...(conTecho && techo.escuadriaId
                ? {
                    techumbre: {
                        aguas: Number(techo.aguas),
                        luz: Number(techo.luz) || 0,
                        largo: Number(techo.largo) || 0,
                        altura_cumbrera: Number(techo.alturaCumbrera) || 0,
                        alero: Number(techo.alero) || 0,
                        unidad: techo.unidad,
                        escuadria_id: Number(techo.escuadriaId),
                        largo_comercial_mm: Number(techo.largoComercialMm),
                        separacion_cerchas: Number(techo.separacionCerchas) || 0,
                        separacion_costaneras: Number(techo.separacionCostaneras) || 0,
                        separacion_unidad: techo.separacionUnidad,
                        merma_pct: Number(techo.mermaPct) || 0,
                        capas: techo.cubiertaId
                            ? [{ producto_capa_id: Number(techo.cubiertaId) }]
                            : [],
                    },
                }
                : {}),
        };
    }, [caras, config, capas, conTecho, techo]);

    // Si lo que hay en pantalla es lo mismo que se guardó la última vez. Se compara
    // una firma en vez de marcar "sin guardar" desde un efecto: un setState dentro
    // de un efecto dispara otro render, y acá pasaría en cada tecla del formulario.
    const estaGuardado = firmaGuardada !== null && firmaGuardada === JSON.stringify({ cuerpo, nombre });

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
    const iniciarSesion = useCallback(async (email, clave) => {
        const { token, usuario: quien } = await entrar(email, clave);

        guardarToken(token);
        setUsuario(quien);
        setMostrandoEntrada(false);
    }, []);

    const cerrarSesion = useCallback(async () => {
        await salir().catch(() => {});

        guardarToken(null);
        guardarProyectoAbierto(null);
        setUsuario(null);
        setProyectoId(null);
        setMios([]);
    }, []);

    const guardar = useCallback(async () => {
        if (!usuario) {
            setMostrandoEntrada(true);
            return;
        }

        if (!cuerpo) return;

        setGuardando(true);

        try {
            // `cuerpo` ya trae la techumbre cuando la etapa está activa, así que
            // guardar y calcular mandan exactamente lo mismo.
            const payload = {
                ...cuerpo,
                nombre,
                planta: {
                    largo_mm: aMm(planta.largo, planta.unidad),
                    ancho_mm: aMm(planta.ancho, planta.unidad),
                    alto_mm: aMm(planta.alto, planta.unidad),
                    unidad_ingreso: planta.unidad,
                },
            };

            const guardado = proyectoId
                ? await actualizarProyecto(proyectoId, payload)
                : await crearProyecto(payload);

            setProyectoId(guardado.id);
            guardarProyectoAbierto(guardado.id);
            setFirmaGuardada(JSON.stringify({ cuerpo, nombre }));
            listarProyectos().then((d) => setMios(d.proyectos)).catch(() => {});
        } catch (e) {
            setError(e.message);
        } finally {
            setGuardando(false);
        }
    }, [usuario, cuerpo, nombre, planta, proyectoId]);

    /**
     * Congela el presupuesto en la base.
     *
     * Exige el proyecto guardado y sin cambios pendientes: un presupuesto que
     * apunta a una geometría distinta de la que se usó para calcularlo no se
     * puede auditar después. Y exige todos los precios, porque emitir con
     * huecos produce un documento que parece completo y no lo está.
     */
    const emitir = useCallback(async () => {
        if (!proyectoId || !estaGuardado) return;

        setGuardando(true);

        try {
            const numericos = Object.fromEntries(
                Object.entries(precios)
                    .filter(([, v]) => v !== '' && v != null)
                    .map(([k, v]) => [k, Number(v)]),
            );

            setEmitido(await emitirPresupuesto(proyectoId, numericos, 'CLP'));
            setError(null);
        } catch (e) {
            setError(e.message);
        } finally {
            setGuardando(false);
        }
    }, [proyectoId, estaGuardado, precios]);

    const abrir = useCallback(async (id) => {
        try {
            const datos = desdeApi(await abrirProyecto(id));

            setProyectoId(id);
            guardarProyectoAbierto(id);
            setNombre(datos.nombre);
            setPlanta(datos.planta);
            setVanos(datos.vanos);
            if (datos.config) setConfig((c) => ({ ...c, ...datos.config }));
            setCapas((c) => ({ ...c, ...datos.capas }));
            setConTecho(Boolean(datos.techo));
            if (datos.techo) setTecho((t) => ({ ...t, ...datos.techo }));
            setPrecios({});
            setFirmaGuardada(null);
            setEmitido(null);
        } catch (e) {
            setError(e.message);
        }
    }, []);

// Recupera el proyecto que estaba abierto al recargar la página. Sólo la
    // primera vez tras entrar: después, cambiar de proyecto es cosa del usuario.
    const yaRecupere = useRef(false);

    useEffect(() => {
        if (!usuario || !catalogo || yaRecupere.current) return;

        const guardado = leerProyectoAbierto();

        if (guardado) {
            yaRecupere.current = true;
            abrir(guardado);
        }
    }, [usuario, catalogo, abrir]);

    const materiales = useMemo(() => resultado?.materiales ?? [], [resultado]);

    const total = useMemo(
        () => materiales.reduce((s, m) => s + (Number(precios[m.clave]) || 0) * m.cantidad_comprar, 0),
        [materiales, precios],
    );

    const faltantes = materiales.filter((m) => !precios[m.clave]).length;
    const enMm = (valor) => (Number(valor) || 0) * { m: 1000, cm: 10, mm: 1, ft: 304.8, in: 25.4 }[planta.unidad];

    if (mostrandoEntrada) {
        return <Entrada onEntro={iniciarSesion} onCancelar={() => setMostrandoEntrada(false)} />;
    }

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
            <BarraSuperior
                titulo="Presupuesto"
                fecha={HOY}
                onVolver={() => {}}
                usuario={usuario}
                onEntrar={() => setMostrandoEntrada(true)}
                onSalir={cerrarSesion}
            />

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
                            <label className="campo">
                                <span className="campo__rotulo">Nombre del proyecto</span>
                                <span className="campo__control">
                                    <input
                                        className="entrada"
                                        value={nombre}
                                        onChange={(e) => setNombre(e.target.value)}
                                    />
                                </span>
                            </label>

                            <Boton onClick={guardar} disabled={guardando || estaGuardado}>
                                {guardando
                                    ? 'Guardando…'
                                    : estaGuardado
                                        ? 'Guardado'
                                        : usuario
                                            ? (proyectoId ? 'Guardar cambios' : 'Guardar proyecto')
                                            : 'Entrar para guardar'}
                            </Boton>

                            {mios.length > 0 && (
                                <label className="campo">
                                    <span className="campo__rotulo">Abrir uno guardado</span>
                                    <span className="campo__control">
                                        <select
                                            className="selector"
                                            value={proyectoId ?? ''}
                                            onChange={(e) => e.target.value && abrir(Number(e.target.value))}
                                        >
                                            <option value="">— elegir —</option>
                                            {mios.map((p) => (
                                                <option key={p.id} value={p.id}>{p.nombre}</option>
                                            ))}
                                        </select>
                                    </span>
                                </label>
                            )}
                        </Panel>
                    )}

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
                                De estas tres medidas salen las cuatro caras.
                            </p>
                        </Panel>
                    )}

                    {seccion === 'proyecto' && (
                        <Panel style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            <h2 className="titulo" style={{ margin: 0, fontSize: '1.05rem' }}>
                                Puertas y ventanas
                            </h2>

                            <EditorVanos
                                caras={caras}
                                vanos={vanos}
                                diagnostico={resultado?.caras}
                                onVanos={(i, lista) => setVanos((v) => ({ ...v, [i]: lista }))}
                            />
                        </Panel>
                    )}

                    {seccion === 'etapas' && catalogo && (
                        <PanelConfiguracion
                            catalogo={catalogo}
                            config={config}
                            onConfig={(cambio) => setConfig((c) => ({ ...c, ...cambio }))}
                            capas={capas}
                            onCapa={(tipo, id) => setCapas((c) => ({ ...c, [tipo]: id }))}
                            perdida={resultado?.corte?.perdida_calculada_pct ?? null}
                        />
                    )}

                    {seccion === 'etapas' && catalogo && (
                        <PanelTechumbre
                            catalogo={catalogo}
                            techo={techo}
                            onTecho={(cambio) => setTecho((t) => ({ ...t, ...cambio }))}
                            activa={conTecho}
                            onActivar={setConTecho}
                            resultado={resultado?.techumbre ?? null}
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
                            <Boton
                                principal
                                disabled={materiales.length === 0}
                                onClick={() => setVista(vista === 'informe' ? 'plano' : 'informe')}
                            >
                                {vista === 'informe'
                                    ? 'Volver al plano'
                                    : faltantes > 0
                                        ? `Ver presupuesto (faltan ${faltantes} precios)`
                                        : 'Ver presupuesto'}
                            </Boton>
                        </>
                    )}
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minHeight: 0 }}>
                    {vista === 'informe' && resultado ? (
                        <Presupuesto
                            materiales={materiales}
                            precios={precios}
                            obra={resultado.obra}
                            corte={resultado.corte}
                            proyecto={`${planta.largo} × ${planta.ancho} × ${planta.alto} ${planta.unidad}`}
                            onEmitir={emitir}
                            emitido={emitido}
                            emitiendo={guardando}
                            onDescargar={() => descargarPdf(proyectoId, emitido.id).catch((e) => setError(e.message))}
                            puedeEmitir={Boolean(proyectoId) && estaGuardado && faltantes === 0}
                            motivoNoEmite={
                                !usuario
                                    ? 'Hay que entrar para poder guardar un presupuesto.'
                                    : !proyectoId
                                        ? 'Primero guarda el proyecto, en la pestaña Proyecto.'
                                        : !estaGuardado
                                            ? 'Hay cambios sin guardar: un presupuesto que apunta a otra geometría no se puede auditar después.'
                                            : faltantes > 0
                                                ? `Faltan ${faltantes} precios. Emitir con huecos daría un documento que parece completo y no lo está.`
                                                : null
                            }
                        />
                    ) : (
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
                            espesorPiezaMm={resultado?.tabiqueria?.espesor_pieza_mm ?? 41}
                            // Los vanos ya convertidos a milímetros vienen del
                            // backend, que es quien sabe convertir unidades.
                            vanosPorCara={Object.fromEntries(
                                (resultado?.caras ?? []).map((c, i) => [i, c.vanos]),
                            )}
                            filasCadenetas={resultado?.tabiqueria?.filas_cadenetas ?? 0}
                            techo={
                                conTecho && resultado?.techumbre
                                    ? {
                                        aguas: Number(techo.aguas),
                                        alturaCumbrera: (Number(techo.alturaCumbrera) || 0) * { m: 1000, cm: 10, mm: 1, ft: 304.8, in: 25.4 }[techo.unidad],
                                        alero: (Number(techo.alero) || 0) * { m: 1000, cm: 10, mm: 1, ft: 304.8, in: 25.4 }[techo.unidad],
                                        separacionCerchas: (Number(techo.separacionCerchas) || 1)
                                            * { m: 1000, cm: 10, mm: 1, ft: 304.8, in: 25.4 }[techo.separacionUnidad],
                                        separacionCostaneras: (Number(techo.separacionCostaneras) || 1)
                                            * { m: 1000, cm: 10, mm: 1, ft: 304.8, in: 25.4 }[techo.separacionUnidad],
                                    }
                                    : null
                            }
                        />
                    </Panel>
                    )}

                    {resultado && vista === 'plano' && (
                        <Panel className="veta" style={{ display: 'flex', flexWrap: 'wrap', gap: 18, justifyContent: 'space-around' }}>
                            {[
                                ['Superficie neta', `${resultado.obra.superficie_neta_m2.toFixed(2)} m²`],
                                ['Cavidad real', `${resultado.obra.cavidad_m2.toFixed(2)} m²`],
                                ['Piezas a cortar', resultado.obra.piezas],
                                ['Tiras a comprar', resultado.corte.tiras_a_comprar],
                                // Recorte y aserrín van calculados, no estimados:
                                // el primero sale del empaquetado y el segundo de
                                // contar los cortes.
                                ['Recorte', `${resultado.corte.desperdicio_m.toFixed(1)} m`],
                                ['Aserrín', `${resultado.corte.aserrin_m.toFixed(2)} m`],
                                ['Pérdida real', `${resultado.corte.perdida_calculada_pct.toFixed(1)} %`],
                                ...(resultado.techumbre
                                    ? [
                                        ['Pendiente', `${resultado.techumbre.pendiente_pct} %`],
                                        ['Cerchas', resultado.techumbre.cerchas],
                                        ['Faldón', `${resultado.techumbre.superficie_m2.toFixed(2)} m²`],
                                        ['Tiras del techo', resultado.techumbre.corte.tiras_a_comprar],
                                    ]
                                    : []),
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
