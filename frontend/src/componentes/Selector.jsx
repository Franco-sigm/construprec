/**
 * Lista desplegable con rótulo. Se usa para elegir del catálogo.
 */
export default function Selector({ rotulo, valor, onCambiar, opciones, nota, permiteVacio = false }) {
    return (
        <label className="campo">
            <span className="campo__rotulo">{rotulo}</span>

            <span className="campo__control">
                <select
                    className="selector"
                    value={valor ?? ''}
                    onChange={(e) => onCambiar(e.target.value === '' ? null : e.target.value)}
                >
                    {permiteVacio && <option value="">— sin esta capa —</option>}
                    {opciones.map((o) => (
                        <option key={o.valor} value={o.valor}>{o.rotulo}</option>
                    ))}
                </select>
            </span>

            {nota && <span className="campo__nota">{nota}</span>}
        </label>
    );
}
