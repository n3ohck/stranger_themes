import { defineStore } from 'pinia';
import { api, conParametros } from '../api/cliente';
import { descuentoUnitario } from '../../shared/descuento';
import { useCatalogo } from './catalogo';

let contador = 0;

/** 'YYYY-MM-DD' de hoy, en hora local. */
function hoy() {
    const d = new Date();
    return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
}

/**
 * Carrito de la venta en curso.
 *
 * Una línea lleva UNA fecha, UNA hora y UN nombre de cliente. Los recorridos de un
 * paquete se encadenan solos a partir de la hora elegida: el servidor devuelve los
 * tramos junto con el horario, así que el cajero no captura tres veces lo mismo ni
 * puede dejar dos recorridos del mismo paquete a nombre de personas distintas.
 *
 * `cantidad` es el número de participantes. Antes había un contador de cantidad y
 * otro de personas por reservación que se desincronizaban; ahora es un solo valor.
 *
 * Los importes que se muestran replican la fórmula del servidor, pero al cobrar solo
 * se envían producto, cantidad, código y horario: el backend recalcula.
 */
export const useCarrito = defineStore('carrito', {
    state: () => ({
        lineas: [],
        cliente: { nombre: '' },
        guardando: false,
        // Copia de la última venta cobrada, para el botón de duplicar.
        ultima: null,
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

        /** Líneas que necesitan horario y todavía no lo tienen. */
        sinHorario(state) {
            return state.lineas.filter((l) => l.producto.requiere_reservacion && !l.hora);
        },

        puedeCobrar() {
            return !this.vacio && this.sinHorario.length === 0;
        },

        puedeDuplicar: (state) => !!state.ultima,
    },

    actions: {
        agregar(producto, cantidad = 1) {
            // Los artículos se acumulan; los recorridos no, porque cada uno lleva
            // su propio horario.
            const existente = this.lineas.find(
                (l) => l.producto.id === producto.id && !l.producto.requiere_reservacion
            );

            if (existente) {
                existente.cantidad += cantidad;
                return existente;
            }

            const linea = {
                uid: `l${++contador}`,
                producto,
                cantidad,
                cliente: '',
                codigo_descuento: '',
                descuento: null,
                fecha: producto.requiere_reservacion ? hoy() : '',
                hora: '',
                tramos: [],
                horarios: [],
                cargandoHorarios: false,
                avisoHorarios: '',
            };

            this.lineas.push(linea);

            if (linea.producto.requiere_reservacion) {
                this.cargarHorarios(linea.uid);
            }

            return linea;
        },

        quitar(uid) {
            this.lineas = this.lineas.filter((l) => l.uid !== uid);
        },

        cambiarCantidad(uid, cantidad) {
            const linea = this.lineas.find((l) => l.uid === uid);
            if (!linea) return;

            const maximo = linea.producto.capacidad || 99;
            linea.cantidad = Math.min(maximo, Math.max(1, Math.round(cantidad) || 1));
        },

        cambiarFecha(uid, fecha) {
            const linea = this.lineas.find((l) => l.uid === uid);
            if (!linea) return;

            linea.fecha = fecha;
            linea.hora = '';
            linea.tramos = [];
            this.cargarHorarios(uid);
        },

        elegirHorario(uid, inicio) {
            const linea = this.lineas.find((l) => l.uid === uid);
            if (!linea) return;

            const horario = linea.horarios.find((h) => h.inicio === inicio);
            if (!horario || !horario.libre) return;

            linea.hora = inicio;
            linea.tramos = horario.tramos;
        },

        /**
         * Horarios que la sucursal tiene configurados para ese día. Vienen del
         * mismo motor que alimenta la lista de reservas y la tienda en línea.
         */
        async cargarHorarios(uid) {
            const linea = this.lineas.find((l) => l.uid === uid);
            if (!linea || !linea.producto.requiere_reservacion || !linea.fecha) return;

            linea.cargandoHorarios = true;
            linea.avisoHorarios = '';

            try {
                const datos = await api.get(conParametros('disponibilidad', {
                    producto_id: linea.producto.id,
                    fecha: linea.fecha,
                }));

                linea.horarios = datos.horarios;

                if (!datos.abre) {
                    linea.avisoHorarios = 'La sucursal no abre ese día.';
                } else if (!datos.horarios.some((h) => h.libre)) {
                    linea.avisoHorarios = 'No quedan horarios libres ese día.';
                }
            } catch (e) {
                linea.horarios = [];
                linea.avisoHorarios = e.listaErrores?.[0] || e.message;
            } finally {
                linea.cargandoHorarios = false;
            }
        },

        aplicarDescuento(uid, codigo) {
            const linea = this.lineas.find((l) => l.uid === uid);
            if (!linea) return 'Línea no encontrada.';

            if (!codigo) {
                linea.codigo_descuento = '';
                linea.descuento = null;
                return null;
            }

            const descuento = useCatalogo().buscarDescuento(codigo);

            if (!descuento) return 'El código no existe o no está activo.';

            if (descuento.producto_tipo && descuento.producto_tipo !== linea.producto.tipo) {
                return `El código ${descuento.codigo} no aplica para ${linea.producto.nombre}.`;
            }

            linea.codigo_descuento = descuento.codigo;
            linea.descuento = descuento;

            return null;
        },

        limpiar() {
            this.lineas = [];
            this.cliente = { nombre: '' };
        },

        /**
         * Rehace la venta anterior. Sirve cuando llega un grupo que paga por
         * separado: en vez de recapturar todo, se repite y se ajusta lo poco que
         * cambie. Los horarios se vuelven a consultar porque el que se usó pudo
         * haberse ocupado con la venta anterior.
         */
        duplicar() {
            if (!this.ultima) return;

            this.limpiar();
            this.cliente = { ...this.ultima.cliente };

            this.ultima.lineas.forEach((guardada) => {
                const producto = useCatalogo().porId(guardada.producto_id);
                if (!producto) return;

                const linea = this.agregar(producto, guardada.cantidad);
                linea.cliente = guardada.cliente;
                linea.fecha = guardada.fecha || linea.fecha;

                if (guardada.codigo_descuento) {
                    this.aplicarDescuento(linea.uid, guardada.codigo_descuento);
                }

                if (linea.producto.requiere_reservacion) {
                    this.cargarHorarios(linea.uid);
                }
            });
        },

        async cobrar(pagos) {
            this.guardando = true;

            try {
                const carga = {
                    items: this.lineas.map((linea) => ({
                        producto_id: linea.producto.id,
                        cantidad: linea.cantidad,
                        codigo_descuento: linea.codigo_descuento || null,
                        cliente: linea.cliente || this.cliente.nombre || null,
                        // Un tramo por recorrido, con la misma gente y el mismo
                        // nombre: el paquete es una sola reservación repartida.
                        reservaciones: (linea.tramos || []).map((t) => ({
                            producto_id: t.producto_id,
                            nombre: linea.cliente || this.cliente.nombre || null,
                            personas: linea.cantidad,
                            fecha: t.inicio.split(' ')[0],
                            hora: t.inicio.split(' ')[1].slice(0, 5),
                        })),
                    })),
                    pagos: pagos
                        .filter((p) => Number(p.monto) > 0)
                        .map((p) => ({
                            tipo: p.tipo,
                            monto: Number(p.monto),
                            referencia: p.referencia || null,
                        })),
                    cliente: { nombre: this.cliente.nombre || null },
                };

                const datos = await api.post('ventas', carga);

                // Se guarda antes de limpiar, para poder duplicarla.
                this.ultima = {
                    cliente: { ...this.cliente },
                    lineas: this.lineas.map((l) => ({
                        producto_id: l.producto.id,
                        cantidad: l.cantidad,
                        cliente: l.cliente,
                        fecha: l.fecha,
                        codigo_descuento: l.codigo_descuento,
                    })),
                };

                this.limpiar();

                return datos.venta;
            } finally {
                this.guardando = false;
            }
        },
    },
});
