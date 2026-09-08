/**
 * Cliente de la API.
 *
 * La base sale de una variable de entorno de Vite: en desarrollo apunta al
 * `artisan serve` local y en el hosting al dominio real, sin recompilar a mano.
 */
const BASE = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api';

const LLAVE_TOKEN = 'construprec.token';

/*
 * El token vive en localStorage.
 *
 * No es lo más seguro que existe —un script inyectado en la página podría
 * leerlo— pero la alternativa, una cookie HttpOnly, exige que la API y el sitio
 * compartan dominio, y acá viven separados. Entre las dos, localStorage con la
 * API en otro origen es lo que corresponde; lo que protege de verdad es no
 * inyectar scripts de terceros en la página.
 */
export function guardarToken(token) {
    try {
        if (token) localStorage.setItem(LLAVE_TOKEN, token);
        else localStorage.removeItem(LLAVE_TOKEN);
    } catch {
        // Ventana privada o almacenamiento bloqueado: la sesión dura lo que dure
        // la pestaña, que es peor pero no impide trabajar.
    }
}

export function leerToken() {
    try {
        return localStorage.getItem(LLAVE_TOKEN);
    } catch {
        return null;
    }
}

async function pedir(ruta, opciones = {}) {
    const token = leerToken();

    const respuesta = await fetch(`${BASE}${ruta}`, {
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        ...opciones,
    });

    const cuerpo = await respuesta.json().catch(() => null);

    if (respuesta.status === 401) {
        // El token venció o fue revocado. Se limpia acá y no en cada pantalla,
        // para que no queden llamadas repitiendo una credencial muerta.
        guardarToken(null);
        const error = new Error('La sesión venció. Vuelve a entrar.');
        error.sinSesion = true;
        throw error;
    }

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

// --- sesión ---

export const entrar = (email, password) => pedir('/login', {
    method: 'POST',
    body: JSON.stringify({ email, password, dispositivo: 'navegador' }),
});

export const salir = () => pedir('/logout', { method: 'POST' });

export const quienSoy = () => pedir('/yo');

// --- proyectos ---

export const listarProyectos = () => pedir('/proyectos');

export const abrirProyecto = (id) => pedir(`/proyectos/${id}`);

export const crearProyecto = (cuerpo) => pedir('/proyectos', {
    method: 'POST',
    body: JSON.stringify(cuerpo),
});

export const actualizarProyecto = (id, cuerpo) => pedir(`/proyectos/${id}`, {
    method: 'PUT',
    body: JSON.stringify(cuerpo),
});

/**
 * Descarga el PDF del presupuesto.
 *
 * Va por fetch y no por un enlace directo porque la ruta pide token en la
 * cabecera, y un <a href> no puede mandarlo. Se recibe el archivo, se crea un
 * enlace temporal y se dispara el guardado.
 */
export async function descargarPdf(proyectoId, presupuestoId) {
    const respuesta = await fetch(
        `${BASE}/proyectos/${proyectoId}/presupuestos/${presupuestoId}/pdf`,
        { headers: { Accept: 'application/pdf', Authorization: `Bearer ${leerToken()}` } },
    );

    if (!respuesta.ok) {
        throw new Error('No se pudo generar el PDF.');
    }

    const blob = await respuesta.blob();
    const url = URL.createObjectURL(blob);

    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = `presupuesto-${presupuestoId}.pdf`;
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();

    // Sin esto el blob queda en memoria hasta que se cierre la pestaña.
    URL.revokeObjectURL(url);
}

export const emitirPresupuesto = (proyectoId, precios, moneda = 'CLP') =>
    pedir(`/proyectos/${proyectoId}/presupuestos`, {
        method: 'POST',
        body: JSON.stringify({ precios, moneda }),
    });
