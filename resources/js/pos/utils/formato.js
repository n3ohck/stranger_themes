/**
 * Formateo compartido. El POS muestra dinero en muchos lugares y basta con una
 * diferencia de formato para que el cajero dude de la cifra.
 */

const monedaMX = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
});

export function dinero(valor) {
    return monedaMX.format(Number(valor) || 0);
}

export function numero(valor, decimales = 2) {
    return (Number(valor) || 0).toFixed(decimales);
}

const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

/**
 * El API entrega fechas como 'YYYY-MM-DD HH:mm:ss' sin zona. Se parsean a mano
 * porque `new Date('2026-08-17 18:00:00')` no es confiable entre navegadores, y
 * en Safari devuelve Invalid Date.
 */
export function aFecha(texto) {
    if (!texto) return null;
    if (texto instanceof Date) return texto;

    const [fecha, hora = '00:00:00'] = String(texto).split(/[ T]/);
    const [anio, mes, dia] = fecha.split('-').map(Number);
    const [h, m, s] = hora.split(':').map(Number);

    return new Date(anio, (mes || 1) - 1, dia || 1, h || 0, m || 0, s || 0);
}

export function horaCorta(texto) {
    const fecha = aFecha(texto);
    if (!fecha) return '';

    return fecha.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: true });
}

export function fechaCorta(texto) {
    const fecha = aFecha(texto);
    if (!fecha) return '';

    return `${fecha.getDate()} ${MESES[fecha.getMonth()].slice(0, 3)}`;
}

export function fechaLarga(texto) {
    const fecha = aFecha(texto);
    if (!fecha) return '';

    return `${DIAS[fecha.getDay()]} ${fecha.getDate()} de ${MESES[fecha.getMonth()]}`;
}

export function fechaHora(texto) {
    const fecha = aFecha(texto);
    if (!fecha) return '';

    return `${fechaCorta(texto)} ${horaCorta(texto)}`;
}

/** 'YYYY-MM-DD' en hora local, que es lo que espera el API. */
export function aIsoFecha(fecha) {
    const d = fecha instanceof Date ? fecha : aFecha(fecha);
    if (!d) return '';

    return [
        d.getFullYear(),
        String(d.getMonth() + 1).padStart(2, '0'),
        String(d.getDate()).padStart(2, '0'),
    ].join('-');
}

export function sumarDias(fecha, dias) {
    const d = new Date(fecha instanceof Date ? fecha.getTime() : aFecha(fecha).getTime());
    d.setDate(d.getDate() + dias);
    return d;
}

export function inicioDeSemana(fecha) {
    const d = new Date(fecha.getTime());
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() - d.getDay());
    return d;
}
