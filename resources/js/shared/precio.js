/**
 * Precio por persona según el tamaño del grupo. Espejo de Producto::precioPara().
 *
 * Lo comparten el punto de venta y la tienda en línea solo para mostrar el total
 * antes de cobrar: el servidor recalcula y manda él. Si la regla cambia en el
 * backend, hay que cambiarla aquí también.
 *
 * `rangos` llega del catálogo ya ordenado: [{ desde, hasta, precio }], con
 * `hasta` null para "en adelante". El rango del grupo fija el precio de todas
 * sus personas; si ninguno aplica, se cobra el precio base.
 */
export function precioPorPersona(producto, personas) {
    const rango = (producto?.rangos_precio || []).find(
        (r) => personas >= r.desde && (r.hasta === null || personas <= r.hasta)
    );

    return rango ? Number(rango.precio) : Number(producto?.precio || 0);
}

/** "1 a 3", "4 a 6", "7 o más". */
export function etiquetaRango(rango) {
    if (rango.hasta === null) return `${rango.desde} o más`;
    if (rango.hasta === rango.desde) return `${rango.desde}`;
    return `${rango.desde} a ${rango.hasta}`;
}
