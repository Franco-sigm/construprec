/**
 * Un número con su unidad al lado.
 *
 * Es la pieza que hace cumplir la regla del producto: toda medida deja elegir en
 * qué unidad se escribe, incluso si el resto del proyecto va en otra. La
 * conversión la hace el backend, que guarda todo en milímetros enteros; acá sólo
 * se manda el par número + unidad.
 */

const UNIDADES = [
    { valor: 'm', rotulo: 'metros' },
    { valor: 'cm', rotulo: 'cm' },
    { valor: 'mm', rotulo: 'mm' },
    { valor: 'ft', rotulo: 'pies' },
    { valor: 'in', rotulo: 'pulgadas' },
];

export default function CampoMedida({
    rotulo,
    valor,
    unidad,
    onValor,
    onUnidad,
    nota,
    paso = 'any',
    unidades = UNIDADES,
}) {
    return (
        <label className="campo">
            <span className="campo__rotulo">{rotulo}</span>

            <span className="campo__control">
                <input
                    className="entrada"
                    type="number"
                    min="0"
                    step={paso}
                    inputMode="decimal"
                    value={valor}
                    onChange={(e) => onValor(e.target.value)}
                />

                <select
                    className="selector selector--unidad"
                    value={unidad}
                    onChange={(e) => onUnidad(e.target.value)}
                    aria-label={`Unidad de ${rotulo.toLowerCase()}`}
                >
                    {unidades.map((u) => (
                        <option key={u.valor} value={u.valor}>{u.rotulo}</option>
                    ))}
                </select>
            </span>

            {nota && <span className="campo__nota">{nota}</span>}
        </label>
    );
}
