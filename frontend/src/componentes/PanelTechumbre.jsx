import CampoMedida from './CampoMedida';
import Panel from './Panel';
import Selector from './Selector';

/**
 * Configuración de la techumbre.
 *
 * La pendiente se define por la altura de cumbrera y no por un ángulo, porque es
 * lo que se decide primero: cuánto puede subir el techo sobre el muro. El ángulo
 * y el porcentaje se muestran calculados, para no obligar a nadie a resolverlos
 * de cabeza.
 */
export default function PanelTechumbre({
    catalogo,
    techo,
    onTecho,
    activa,
    onActivar,
    resultado,
}) {
    const cubiertas = catalogo.productos.cubierta ?? [];
    const elegida = cubiertas.find((p) => String(p.id) === String(techo.cubiertaId));

    return (
        <Panel style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <h2 className="titulo" style={{ margin: 0, fontSize: '1.05rem' }}>Techumbre</h2>

            <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}>
                <input
                    type="checkbox"
                    checked={activa}
                    onChange={(e) => onActivar(e.target.checked)}
                    style={{ width: 18, height: 18 }}
                />
                <span className="campo__rotulo" style={{ margin: 0 }}>Calcular el techo</span>
            </label>

            {!activa && (
                <p className="campo__nota" style={{ margin: 0 }}>
                    El proyecto se queda en los muros hasta que actives esta etapa.
                </p>
            )}

            {activa && (
                <>
                    <Selector
                        rotulo="Aguas"
                        valor={techo.aguas}
                        onCambiar={(v) => onTecho({ aguas: Number(v) })}
                        opciones={[
                            { valor: 1, rotulo: '1 — un solo faldón' },
                            { valor: 2, rotulo: '2 — con caballete al centro' },
                        ]}
                        nota={
                            techo.aguas === 2
                                ? 'Cada faldón cubre la mitad de la luz.'
                                : 'El faldón único cubre la luz completa, así que el par sale mucho más largo.'
                        }
                    />

                    <CampoMedida
                        rotulo="Luz entre muros"
                        valor={techo.luz}
                        unidad={techo.unidad}
                        onValor={(v) => onTecho({ luz: v })}
                        onUnidad={(u) => onTecho({ unidad: u })}
                        nota="La distancia que tiene que cruzar el tirante."
                    />

                    <CampoMedida
                        rotulo="Largo del techo"
                        valor={techo.largo}
                        unidad={techo.unidad}
                        onValor={(v) => onTecho({ largo: v })}
                        onUnidad={(u) => onTecho({ unidad: u })}
                        mostrarUnidad={false}
                    />

                    <CampoMedida
                        rotulo="Altura de cumbrera"
                        valor={techo.alturaCumbrera}
                        unidad={techo.unidad}
                        onValor={(v) => onTecho({ alturaCumbrera: v })}
                        onUnidad={(u) => onTecho({ unidad: u })}
                        mostrarUnidad={false}
                        nota={
                            resultado
                                ? `Cuánto sube el techo sobre el muro. Da ${resultado.pendiente_pct} % de pendiente (${resultado.pendiente_grados}°).`
                                : 'Cuánto sube el techo sobre el muro. De acá sale la pendiente.'
                        }
                    />

                    <CampoMedida
                        rotulo="Alero"
                        valor={techo.alero}
                        unidad={techo.unidad}
                        onValor={(v) => onTecho({ alero: v })}
                        onUnidad={(u) => onTecho({ unidad: u })}
                        mostrarUnidad={false}
                        nota="Medido en horizontal, que es como se mira desde abajo."
                    />

                    <Selector
                        rotulo="Escuadría de la cercha"
                        valor={techo.escuadriaId}
                        onCambiar={(v) => onTecho({ escuadriaId: v })}
                        opciones={catalogo.escuadrias.map((e) => ({
                            valor: e.id,
                            rotulo: `${e.descripcion} · ${e.ancho_real_mm} × ${e.alto_real_mm} mm`,
                        }))}
                        nota="El tirante es la pieza más larga y suele definirla."
                    />

                    <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) minmax(0,1fr)', gap: 10 }}>
                        <CampoMedida
                            rotulo="Entre cerchas"
                            valor={techo.separacionCerchas}
                            unidad={techo.separacionUnidad}
                            onValor={(v) => onTecho({ separacionCerchas: v })}
                            onUnidad={(u) => onTecho({ separacionUnidad: u })}
                        />
                        <CampoMedida
                            rotulo="Entre costaneras"
                            valor={techo.separacionCostaneras}
                            unidad={techo.separacionUnidad}
                            onValor={(v) => onTecho({ separacionCostaneras: v })}
                            onUnidad={(u) => onTecho({ separacionUnidad: u })}
                            mostrarUnidad={false}
                        />
                    </div>

                    <Selector
                        rotulo="Cubierta"
                        valor={techo.cubiertaId}
                        permiteVacio
                        onCambiar={(v) => {
                            // Cada cubierta trae la separación de costaneras que
                            // soporta: proponerla evita tener que buscarla en la
                            // ficha del fabricante.
                            const p = cubiertas.find((c) => String(c.id) === String(v));

                            onTecho({
                                cubiertaId: v,
                                ...(p?.separacion_costaneras_mm
                                    ? {
                                        separacionCostaneras: String(p.separacion_costaneras_mm / 1000),
                                        separacionUnidad: 'm',
                                    }
                                    : {}),
                            });
                        }}
                        opciones={cubiertas.map((p) => ({ valor: p.id, rotulo: p.nombre }))}
                        nota={
                            elegida?.requiere_tablero
                                ? 'Va sobre tablero continuo, no sobre costaneras: hay que sumar OSB o terciado.'
                                : elegida?.separacion_costaneras_mm
                                    ? `Admite costaneras cada ${elegida.separacion_costaneras_mm / 1000} m, y se ajustó a eso.`
                                    : null
                        }
                    />

                    <CampoMedida
                        rotulo="Descarte de la madera"
                        valor={techo.mermaPct}
                        unidad="%"
                        onValor={(v) => onTecho({ mermaPct: v })}
                        onUnidad={() => {}}
                        unidades={[{ valor: '%', rotulo: '%' }]}
                        paso="1"
                    />
                </>
            )}
        </Panel>
    );
}
