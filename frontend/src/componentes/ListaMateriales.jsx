import Panel from './Panel';

/**
 * La lista de materiales que dedujo el backend.
 *
 * Cuando `conPrecios` está activo, cada fila suma una casilla para escribir el
 * precio: es el paso previo a calcular el total. Las cantidades ya vienen
 * resueltas porque no dependen del precio.
 */
export default function ListaMateriales({ titulo, materiales, conPrecios = false, precios = {}, onPrecio }) {
    const moneda = new Intl.NumberFormat('es-CL');

    return (
        <Panel style={{ display: 'flex', flexDirection: 'column', gap: 10, minHeight: 0 }}>
            <h2
                className="titulo veta"
                style={{
                    margin: 0,
                    padding: '10px 14px',
                    fontSize: '1.15rem',
                    border: '2px solid var(--madera-borde)',
                    borderRadius: 'var(--radio-chico)',
                    boxShadow: 'inset 0 2px 0 rgba(255,240,210,.45), 0 2px 4px rgba(40,25,10,.30)',
                }}
            >
                {titulo}
            </h2>

            <div
                style={{
                    border: '2px solid var(--madera-borde)',
                    borderRadius: 'var(--radio-chico)',
                    overflow: 'auto',
                    boxShadow: 'inset 0 2px 6px rgba(70,40,12,.30)',
                    minHeight: 0,
                }}
            >
                {materiales.map((material) => (
                    <div key={material.clave} className="fila-material">
                        <span className="fila-material__nombre">{material.nombre}</span>

                        <span style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                            <span className="fila-material__cantidad">
                                {moneda.format(material.cantidad_comprar)} {material.unidad_venta}
                            </span>

                            {conPrecios && (
                                <label>
                                    <span className="visualmente-oculto">
                                        Precio unitario de {material.nombre}
                                    </span>
                                    <input
                                        className="campo-precio"
                                        type="number"
                                        min="0"
                                        step="1"
                                        inputMode="numeric"
                                        placeholder="$"
                                        value={precios[material.clave] ?? ''}
                                        onChange={(e) => onPrecio(material.clave, e.target.value)}
                                    />
                                </label>
                            )}
                        </span>
                    </div>
                ))}
            </div>
        </Panel>
    );
}
