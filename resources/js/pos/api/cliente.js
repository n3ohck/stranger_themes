/**
 * Cliente HTTP del punto de venta.
 *
 * Usa fetch directo en vez de axios: el POS solo necesita JSON con un bearer
 * token, y así el bundle no arrastra una dependencia más.
 *
 * Un 401 significa que el JWT expiró (tymon los emite con vida limitada). En
 * ese caso se limpia la sesión y se avisa, para que el cajero vuelva a entrar
 * en lugar de ver errores sueltos a media venta.
 */

const BASE = document.querySelector('meta[name="pos-api"]')?.content || '/pos-api';
const LLAVE_TOKEN = 'pos.token';

let alExpirar = null;

export function alExpirarSesion(callback) {
    alExpirar = callback;
}

export function guardarToken(token) {
    window.localStorage.setItem(LLAVE_TOKEN, token);
}

export function leerToken() {
    return window.localStorage.getItem(LLAVE_TOKEN);
}

export function borrarToken() {
    window.localStorage.removeItem(LLAVE_TOKEN);
}

/**
 * Error con los mensajes de validación de Laravel ya aplanados, para que las
 * pantallas puedan mostrarlos sin repetir la misma lógica de desempaquetado.
 */
export class ErrorApi extends Error {
    constructor(mensaje, estado, errores = {}) {
        super(mensaje);
        this.name = 'ErrorApi';
        this.estado = estado;
        this.errores = errores;
    }

    get listaErrores() {
        return Object.values(this.errores).flat();
    }
}

async function peticion(metodo, ruta, cuerpo = null, opciones = {}) {
    const token = leerToken();

    // Con FormData hay que dejar que el navegador ponga el Content-Type: lleva
    // el boundary del multipart, y fijarlo a mano rompe la subida.
    const esFormData = cuerpo instanceof FormData;

    const respuesta = await fetch(`${BASE}/${ruta.replace(/^\//, '')}`, {
        method: metodo,
        headers: {
            Accept: 'application/json',
            ...(cuerpo && !esFormData ? { 'Content-Type': 'application/json' } : {}),
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: cuerpo ? (esFormData ? cuerpo : JSON.stringify(cuerpo)) : undefined,
    });

    if (respuesta.status === 204) {
        return null;
    }

    let datos = null;

    try {
        datos = await respuesta.json();
    } catch (e) {
        datos = null;
    }

    if (!respuesta.ok) {
        if (respuesta.status === 401 && !opciones.ignorar401) {
            borrarToken();
            if (alExpirar) alExpirar();
        }

        throw new ErrorApi(
            datos?.message || datos?.error || 'No se pudo completar la operación.',
            respuesta.status,
            datos?.errors || {}
        );
    }

    return datos;
}

export const api = {
    get: (ruta, opciones) => peticion('GET', ruta, null, opciones),
    post: (ruta, cuerpo, opciones) => peticion('POST', ruta, cuerpo, opciones),

    /**
     * Envía un formulario con archivo. Los campos nulos se omiten para que
     * Laravel los reciba ausentes y no como la cadena "null".
     */
    subir: (ruta, campos, opciones) => {
        const formulario = new FormData();

        Object.entries(campos).forEach(([clave, valor]) => {
            if (valor === null || valor === undefined || valor === '') return;
            formulario.append(clave, valor);
        });

        return peticion('POST', ruta, formulario, opciones);
    },
};

/**
 * Construye una query string omitiendo los valores vacíos.
 */
export function conParametros(ruta, parametros) {
    const limpios = Object.entries(parametros)
        .filter(([, valor]) => valor !== null && valor !== undefined && valor !== '');

    if (!limpios.length) return ruta;

    return `${ruta}?${new URLSearchParams(limpios).toString()}`;
}
