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

const MENORES = ['', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
    'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho',
    'diecinueve', 'veinte', 'veintiún', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco',
    'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
const DECENAS = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
const CENTENAS = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
    'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

function hastaMil(n) {
    if (n === 100) return 'cien';

    const resto = n % 100;
    const decenas = resto < 30
        ? MENORES[resto]
        : DECENAS[Math.floor(resto / 10)] + (resto % 10 ? ` y ${MENORES[resto % 10]}` : '');

    return [CENTENAS[Math.floor(n / 100)], decenas].filter(Boolean).join(' ');
}

/**
 * Monto con letra al estilo de los recibos: 'CIEN PESOS 00/100 M.N.'. Lo lleva
 * el ticket de pago a empleado, que se firma de recibido.
 */
export function montoConLetra(valor) {
    const centavos = Math.round((Number(valor) || 0) * 100);
    const entero = Math.floor(centavos / 100);

    const millones = Math.floor(entero / 1000000);
    const miles = Math.floor((entero % 1000000) / 1000);
    const unidades = entero % 1000;

    const partes = [];
    if (millones) partes.push(millones === 1 ? 'un millón' : `${hastaMil(millones)} millones`);
    if (miles) partes.push(miles === 1 ? 'mil' : `${hastaMil(miles)} mil`);
    if (unidades) partes.push(hastaMil(unidades));

    let texto = partes.join(' ') || 'cero';
    if (millones && !miles && !unidades) texto += ' de';
    texto += entero === 1 ? ' peso' : ' pesos';

    return `${texto} ${String(centavos % 100).padStart(2, '0')}/100 M.N.`.toUpperCase();
}
