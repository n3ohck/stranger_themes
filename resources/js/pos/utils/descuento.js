/**
 * Regla de redondeo de descuentos, espejo de App\Support\ReglaDescuento.
 *
 * Está duplicada a propósito: el servidor es la autoridad sobre el importe
 * cobrado, pero el cajero necesita ver el total correcto antes de cobrar. Si
 * esta regla cambia en el backend, hay que cambiarla aquí también.
 *
 *   decimal <= .49  -> hacia abajo
 *   decimal == .50  -> se queda igual
 *   decimal >= .51  -> hacia arriba
 */
export function redondearDescuento(valor) {
    const signo = valor < 0 ? -1 : 1;
    const absoluto = Math.abs(valor);
    const decimal = absoluto - Math.floor(absoluto);

    let resultado;

    if (decimal <= 0.49) {
        resultado = Math.floor(absoluto);
    } else if (decimal >= 0.51) {
        resultado = Math.ceil(absoluto);
    } else {
        resultado = absoluto;
    }

    return resultado * signo;
}

export function descuentoUnitario(precio, porcentaje) {
    if (!precio || !porcentaje || precio <= 0 || porcentaje <= 0) return 0;

    return Math.max(0, redondearDescuento(precio * (porcentaje / 100)));
}
