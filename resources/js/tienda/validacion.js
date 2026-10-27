/**
 * Reglas de los datos de contacto, compartidas por los campos y el botón Pagar.
 * El servidor valida lo mismo (CheckoutController::crear); esto solo evita que el
 * cliente llegue a pagar con un dato que se va a rechazar.
 */

/** Dominios que se sugieren al escribir el correo, del más al menos usado. */
export const DOMINIOS_COMUNES = [
    'gmail.com',
    'hotmail.com',
    'outlook.com',
    'yahoo.com',
    'yahoo.com.mx',
    'icloud.com',
    'live.com.mx',
];

export function correoValido(correo) {
    return /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(String(correo).trim());
}

/** Solo los dígitos, sin lada de país: '+52 614 595 6833' → '6145956833'. */
export function digitosTelefono(texto) {
    let digitos = String(texto || '').replace(/\D/g, '');

    if (digitos.length > 10 && digitos.startsWith('52')) digitos = digitos.slice(2);

    return digitos.slice(0, 10);
}

/** '6145956833' → '614 595 6833' */
export function formatoTelefono(texto) {
    const d = digitosTelefono(texto);

    return [d.slice(0, 3), d.slice(3, 6), d.slice(6, 10)].filter(Boolean).join(' ');
}

/** Opcional: vacío o diez dígitos. */
export function telefonoValido(texto) {
    const d = digitosTelefono(texto);

    return d.length === 0 || d.length === 10;
}

/** Distancia de edición, para sugerir 'gmail.com' a quien escribió 'gmial.com'. */
function distancia(a, b) {
    const fila = Array.from({ length: b.length + 1 }, (_, i) => i);

    for (let i = 1; i <= a.length; i++) {
        let anterior = fila[0];
        fila[0] = i;

        for (let j = 1; j <= b.length; j++) {
            const temporal = fila[j];
            fila[j] = Math.min(fila[j] + 1, fila[j - 1] + 1, anterior + (a[i - 1] === b[j - 1] ? 0 : 1));
            anterior = temporal;
        }
    }

    return fila[b.length];
}

/** El dominio común que el cliente seguramente quiso escribir, o null. */
export function dominioCorregido(dominio) {
    const d = String(dominio || '').toLowerCase();
    if (d.length < 5 || DOMINIOS_COMUNES.includes(d)) return null;

    return DOMINIOS_COMUNES.find((comun) => distancia(d, comun) <= 2) || null;
}
