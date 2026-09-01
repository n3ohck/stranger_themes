import { defineStore } from 'pinia';
import { api } from '../api/cliente';
import { descuentoUnitario } from '../../shared/descuento';
import { useCatalogo } from './catalogo';

let contador = 0;

/**
 * Carrito de la venta en curso.
 *
 * Los importes que se muestran aquí replican la fórmula del servidor, pero al
 * cobrar solo se envían producto, cantidad y código: el backend recalcula. Así
 * un error de redondeo en el navegador nunca se convierte en un cobro erróneo.
 */
export const useCarrito = defineStore('carrito', {
    state: () => ({
        lineas: [],
        cliente: { nombre: '', telefono: '', email: '' },
        guardando: false,
    }),

    getters: {
        vacio: (state) => state.lineas.length === 0,

        calculadas(state) {
            return state.lineas.map((linea) => {
                const precio = linea.producto.precio;
                const subtotal = precio * linea.cantidad;
                const porcentaje = linea.descuento?.porcentaje || 0;
                const unitario = descuentoUnitario(precio, porcentaje);
                const descuento = unitario * linea.cantidad;

                return {
                    ...linea,
                    precio,
                    subtotal,
                    descuentoUnitario: unitario,
                    descuento,
                    total: Math.max(0, subtotal - descuento),
                };
            });
        },

        subtotal() {
            return this.calculadas.reduce((suma, l) => suma + l.subtotal, 0);
        },

        totalDescuento() {
            return this.calculadas.reduce((suma, l) => suma + l.descuento, 0);
        },

        total() {
            return this.calculadas.reduce((suma, l) => suma + l.total, 0);
        },

        /**
         * Reservaciones concretas a las que les falta fecha u hora. Se cuentan
         * una por una y no por línea: un paquete de tres tours con dos horarios
         * sin llenar debe reportar dos pendientes, no una.
         */
        reservacionesIncompletas() {
            return this.calculadas.flatMap((linea) => {
                if (!linea.producto.requiere_reservacion) return [];

                return linea.reservaciones
                    .filter((r) => !r.fecha || !r.hora)
                    .map((r) => ({ producto: linea.producto.nombre, etiqueta: r.etiqueta }));
            });
        },

        puedeCobrar() {
            return !this.vacio && this.reservacionesIncompletas.length === 0;
        },
    },

    actions: {
        /**
         * Agrega un producto. Un paquete genera una reservación por cada tour
         * que incluye; un tour suelto, una sola; un artículo, ninguna.
         */
        agregar(producto, cantidad = 1) {
            const existente = this.lineas.find(
                (l) => l.producto.id === producto.id && !l.producto.requiere_reservacion
            );

            // Los artículos se acumulan en una sola línea; los tours no, porque
            // cada uno lleva su propio horario y nombre de cliente.
            if (existente) {
                existente.cantidad += cantidad;
                return existente;
            }

            const plantillas = producto.tours.length
                ? producto.tours.map((tour) => ({ producto_id: tour.id, etiqueta: tour.nombre }))
                : [{ producto_id: producto.id, etiqueta: producto.nombre }];

            const linea = {
                uid: `l${++contador}`,
                producto,
                cantidad,
                codigo_descuento: '',
                descuento: null,
                cliente: '',
                reservaciones: producto.requiere_reservacion
                    ? plantillas.map((plantilla) => ({
                        ...plantilla,
                        nombre: '',
                        personas: cantidad,
                        fecha: '',
                        hora: '',
                    }))
                    : [],
            };

            this.lineas.push(linea);

            return linea;
        },

        quitar(uid) {
            this.lineas = this.lineas.filter((l) => l.uid !== uid);
        },

        cambiarCantidad(uid, cantidad) {
            const linea = this.lineas.find((l) => l.uid === uid);
            if (!linea) return;

            linea.cantidad = Math.max(1, Math.round(cantidad) || 1);

            // Las personas de la reservación siguen a la cantidad mientras el
            // cajero no las ajuste a mano.
            linea.reservaciones.forEach((r) => {
                if (!r.personasEditadas) r.personas = linea.cantidad;
            });
        },

        /**
         * Aplica un código. Devuelve un mensaje de error o null si se aplicó.
         */
        aplicarDescuento(uid, codigo) {
            const linea = this.lineas.find((l) => l.uid === uid);
            if (!linea) return 'Línea no encontrada.';

            if (!codigo) {
                linea.codigo_descuento = '';
                linea.descuento = null;
                return null;
            }

            const catalogo = useCatalogo();
            const descuento = catalogo.buscarDescuento(codigo);

            if (!descuento) {
                return 'El código no existe o no está activo.';
            }

            if (descuento.producto_tipo && descuento.producto_tipo !== linea.producto.tipo) {
                return `El código ${descuento.codigo} no aplica para ${linea.producto.nombre}.`;
            }

            linea.codigo_descuento = descuento.codigo;
            linea.descuento = descuento;

            return null;
        },

        /** Aplica el mismo código a todas las líneas donde sea válido. */
        aplicarDescuentoATodo(codigo) {
            const catalogo = useCatalogo();
            const descuento = catalogo.buscarDescuento(codigo);

            if (!descuento) return 'El código no existe o no está activo.';

            const aplicables = this.lineas.filter(
                (l) => !descuento.producto_tipo || descuento.producto_tipo === l.producto.tipo
            );

            if (!aplicables.length) {
                return `El código ${descuento.codigo} no aplica a ningún producto del carrito.`;
            }

            aplicables.forEach((linea) => {
                linea.codigo_descuento = descuento.codigo;
                linea.descuento = descuento;
            });

            return null;
        },

        limpiar() {
            this.lineas = [];
            this.cliente = { nombre: '', telefono: '', email: '' };
        },

        /**
         * Envía la venta. Se manda la intención, no los importes.
         */
        async cobrar(pagos) {
            this.guardando = true;

            try {
                const carga = {
                    items: this.lineas.map((linea) => ({
                        producto_id: linea.producto.id,
                        cantidad: linea.cantidad,
                        codigo_descuento: linea.codigo_descuento || null,
                        cliente: linea.cliente || null,
                        reservaciones: linea.reservaciones.map((r) => ({
                            producto_id: r.producto_id,
                            nombre: r.nombre || linea.cliente || this.cliente.nombre || null,
                            personas: r.personas,
                            fecha: r.fecha,
                            hora: r.hora,
                        })),
                    })),
                    pagos: pagos
                        .filter((p) => Number(p.monto) > 0)
                        .map((p) => ({
                            tipo: p.tipo,
                            monto: Number(p.monto),
                            referencia: p.referencia || null,
                        })),
                    cliente: {
                        nombre: this.cliente.nombre || null,
                        telefono: this.cliente.telefono || null,
                        email: this.cliente.email || null,
                    },
                };

                const datos = await api.post('ventas', carga);

                this.limpiar();

                return datos.venta;
            } finally {
                this.guardando = false;
            }
        },
    },
});
