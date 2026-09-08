import Boton from './Boton';
import Panel from './Panel';

/**
 * El presupuesto desglosado: una partida por material y el total abajo.
 *
 * Cada línea muestra de dónde salió la cantidad —la superficie a cubrir, lo que
 * rinde una unidad— y no sólo el número final. "16 planchas" obliga a confiar;
 * "16 planchas para cubrir 44 m2, rindiendo 2,9768 cada una" se puede revisar
 * con una calculadora en la mano, que es lo que hace cualquiera antes de gastar
 * medio millón de pesos.
 */

const pesos = new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 });
const decimal = new Intl.NumberFormat('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
// Hasta cuatro decimales: un siding rinde 0,5856 m² y redondearlo a dos lo
// dejaría en 0,59, que no es el número con que se calculó.
const rinde = new Intl.NumberFormat('es-CL', { maximumFractionDigits: 4 });

const ROTULO_ETAPA = { muros: 'Muros', techumbre: 'Techumbre' };

const ROTULO_MAGNITUD = {
    m2: 'Superficie a cubrir',
    ml: 'Madera necesaria',
    m3: 'Volumen a llenar',
    un: 'Unidades necesarias',
};

const UNIDAD = { m2: 'm²', ml: 'm lineales', m3: 'm³', un: 'u' };

/**
 * Concuerda el plural.
 *
 * Todas las unidades de venta terminan en vocal —plancha, tabla, rollo, tira,
 * caja— así que basta agregar la ese. Si alguna vez entra una que termine en
 * consonante habrá que mirarlo, pero inventar reglas de gramática para un caso
 * que no existe sería peor.
 */
function plural(unidad, cantidad) {
    return cantidad === 1 ? unidad : `${unidad}s`;
}

function Partida({ material, precio }) {
    const cantidad = material.cantidad_comprar;
    const subtotal = (Number(precio) || 0) * cantidad;
    const sobra = material.cantidad_comprar - material.cantidad;

    return (
        <article className="partida">
            <h3 className="partida__titulo">
                <span>{material.nombre}</span>
                <span className="partida__subtotal cifra">${pesos.format(Math.round(subtotal))}</span>
            </h3>

            <dl className="partida__datos">
                <dt>{ROTULO_MAGNITUD[material.unidad_magnitud] ?? 'Base de cálculo'}</dt>
                <dd className="cifra">
                    {decimal.format(material.magnitud)} {UNIDAD[material.unidad_magnitud] ?? material.unidad_magnitud}
                </dd>

                {material.rendimiento_m2 && (
                    <>
                        <dt>Rinde</dt>
                        <dd className="cifra">
                            {rinde.format(material.rendimiento_m2)} m² por {material.unidad_venta}
                        </dd>
                    </>
                )}

                <dt>Cantidad</dt>
                <dd className="cifra">
                    {pesos.format(cantidad)} {plural(material.unidad_venta, cantidad)}
                    {sobra > 0.01 && (
                        <span style={{ fontFamily: 'var(--fuente-texto)', fontWeight: 400, color: 'var(--tinta-suave)' }}>
                            {' '}— se ocupan {decimal.format(material.cantidad)}, sobra {decimal.format(sobra)}
                        </span>
                    )}
                </dd>

                <dt>Precio unitario</dt>
                <dd className="cifra">
                    ${pesos.format(Number(precio) || 0)} por {material.unidad_venta}
                </dd>

                {material.merma_pct > 0 && (
                    <>
                        <dt>Descarte incluido</dt>
                        <dd className="cifra">{material.merma_pct} %</dd>
                    </>
                )}
            </dl>
        </article>
    );
}

/** Suma de un grupo de partidas, con los precios que haya cargados. */
function subtotalDe(partidas, precios) {
    return partidas.reduce(
        (suma, m) => suma + (Number(precios[m.clave]) || 0) * m.cantidad_comprar,
        0,
    );
}

export default function Presupuesto({
    materiales,
    precios,
    obra,
    corte,
    proyecto,
    onEmitir,
    emitido,
    puedeEmitir,
    motivoNoEmite,
    emitiendo,
    onDescargar,
}) {
    const total = subtotalDe(materiales, precios);

    // Se agrupa conservando el orden en que vinieron: muros primero, techumbre
    // después, que es el orden en que se construye.
    const etapas = Object.entries(
        materiales.reduce((grupos, m) => {
            (grupos[m.etapa ?? 'muros'] ??= []).push(m);
            return grupos;
        }, {}),
    );

    const sinPrecio = materiales.filter((m) => !precios[m.clave]);

    return (
        <Panel style={{ display: 'flex', flexDirection: 'column', gap: 12, minHeight: 0 }}>
            <h2 className="titulo" style={{ margin: 0, fontSize: '1.2rem' }}>
                {/* El título nombra lo que hay dentro: si sólo hay muros lo dice, y
                    si ya entró el techo pasa a ser el presupuesto de la obra. */}
                {etapas.length > 1 ? 'Presupuesto de la obra' : 'Presupuesto de muros'}
                {proyecto && (
                    <span style={{ fontWeight: 400, textTransform: 'none', fontSize: '0.85rem' }}>
                        {' '}· {proyecto}
                    </span>
                )}
            </h2>

            <div className="informe">
                {obra && (
                    <p className="informe__aviso" style={{ margin: 0 }}>
                        {decimal.format(obra.superficie_bruta_m2)} m² de muro menos{' '}
                        {decimal.format(obra.superficie_vanos_m2)} m² de puertas y ventanas ={' '}
                        {decimal.format(obra.superficie_neta_m2)} m² netos. {obra.piezas} piezas de madera
                        a cortar, {decimal.format(corte.desperdicio_m)} m de recorte
                        ({decimal.format(corte.perdida_calculada_pct)} % de pérdida).
                    </p>
                )}

                {sinPrecio.length > 0 && (
                    <p className="informe__aviso" style={{ margin: 0, color: '#8a5a12' }}>
                        Sin precio todavía: {sinPrecio.map((m) => m.nombre).join(', ')}. El total
                        no los incluye.
                    </p>
                )}

                {/*
                    Agrupado por etapa y con su subtotal. Una lista plana no deja
                    comparar cuánto cuesta el techo aparte de los muros, que es
                    justo la decisión que se toma mirando esto.
                */}
                {etapas.map(([etapa, partidas]) => (
                    <div key={etapa}>
                        {etapas.length > 1 && (
                            <h3 className="etapa">
                                <span>{ROTULO_ETAPA[etapa] ?? etapa}</span>
                                <span className="cifra">
                                    ${pesos.format(Math.round(subtotalDe(partidas, precios)))}
                                </span>
                            </h3>
                        )}

                        {partidas.map((material) => (
                            <Partida key={material.clave} material={material} precio={precios[material.clave]} />
                        ))}
                    </div>
                ))}

                <div className="informe__total">
                    <span>Total</span>
                    <strong className="cifra">${pesos.format(Math.round(total))}</strong>
                </div>
            </div>

            {emitido ? (
                <>
                    <p className="campo__nota" style={{ margin: 0 }}>
                        Guardado como presupuesto <strong>#{emitido.id}</strong> del{' '}
                        {emitido.fecha}, por ${pesos.format(Math.round(emitido.total))}. Queda
                        con los precios de hoy congelados: si mañana suben, este documento no
                        cambia.
                    </p>

                    <Boton principal onClick={onDescargar}>
                        Descargar en PDF
                    </Boton>
                </>
            ) : (
                <>
                    <Boton
                        principal
                        onClick={onEmitir}
                        disabled={!puedeEmitir || emitiendo}
                    >
                        {emitiendo ? 'Guardando…' : 'Guardar este presupuesto'}
                    </Boton>

                    {motivoNoEmite && (
                        <p className="campo__nota" style={{ margin: 0 }}>{motivoNoEmite}</p>
                    )}
                </>
            )}
        </Panel>
    );
}
