/**
 * Cliente de la API.
 *
 * La base sale de una variable de entorno de Vite: en desarrollo apunta al
 * `artisan serve` local y en el hosting al dominio real, sin recompilar a mano.
 */
const BASE = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api';

async function pedir(ruta, opciones = {}) {
    const respuesta = await fetch(`${BASE}${ruta}`, {
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        ...opciones,
    });

    const cuerpo = await respuesta.json().catch(() => null);

    if (!respuesta.ok) {
        // El backend manda 422 con los errores por campo. Se aplana a una lista
        // de frases para poder mostrarlas tal cual: ya vienen en español y
        // nombran el campo, así que traducirlas de nuevo acá sería perder
        // información.
        const detalles = cuerpo?.errors ? Object.values(cuerpo.errors).flat() : [];

        throw new Error(detalles.join(' ') || cuerpo?.message || 'No se pudo conectar con la API.');
    }

    return cuerpo;
}

export const obtenerCatalogo = () => pedir('/catalogo');

export const calcular = (cuerpo) => pedir('/calculos', {
    method: 'POST',
    body: JSON.stringify(cuerpo),
});
