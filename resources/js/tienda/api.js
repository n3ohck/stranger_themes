/**
 * Cliente de la tienda. Público: no lleva token ni sesión.
 */
const BASE = document.querySelector('meta[name="tienda-api"]')?.content || '/tienda-api';

export const SITIO = document.querySelector('meta[name="sitio-web"]')?.content || 'https://strangerthemes.com';

export class ErrorTienda extends Error {
    constructor(mensaje, estado, errores = {}) {
        super(mensaje);
        this.estado = estado;
        this.errores = errores;
    }

    get primero() {
        return Object.values(this.errores).flat()[0] || this.message;
    }
}

async function peticion(metodo, ruta, cuerpo = null) {
    const respuesta = await fetch(`${BASE}/${ruta.replace(/^\//, '')}`, {
        method: metodo,
        headers: {
            Accept: 'application/json',
            ...(cuerpo ? { 'Content-Type': 'application/json' } : {}),
        },
        body: cuerpo ? JSON.stringify(cuerpo) : undefined,
    });

    let datos = null;
    try { datos = await respuesta.json(); } catch (e) { datos = null; }

    if (!respuesta.ok) {
        throw new ErrorTienda(
            datos?.error || datos?.message || 'Algo salió mal. Intenta de nuevo.',
            respuesta.status,
            datos?.errors || {}
        );
    }

    return datos;
}

export const api = {
    catalogo: () => peticion('GET', 'catalogo'),
    disponibilidad: (p) => peticion('GET', `disponibilidad?${new URLSearchParams(p)}`),
    descuento: (p) => peticion('GET', `descuento?${new URLSearchParams(p)}`),
    checkout: (datos) => peticion('POST', 'checkout', datos),
    comprobante: (ref) => peticion('GET', `comprobante/${ref}`),
};

const MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
const DIAS = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];

/** 'YYYY-MM-DD' -> Date local, sin depender del parseo del navegador. */
export function aFecha(iso) {
    const [a, m, d] = String(iso).split(/[- :]/).map(Number);
    return new Date(a, m - 1, d);
}

export function aIso(fecha) {
    return [
        fecha.getFullYear(),
        String(fecha.getMonth() + 1).padStart(2, '0'),
        String(fecha.getDate()).padStart(2, '0'),
    ].join('-');
}

export function fechaLarga(iso) {
    const f = aFecha(iso);
    return `${DIAS[f.getDay()]} ${f.getDate()} de ${MESES[f.getMonth()]}`;
}

export function dinero(v) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v) || 0);
}

/** Quita acentos para comparar con los días guardados en la sucursal. */
export function sinAcentos(t) {
    return String(t).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}
