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

/** Minutos desde medianoche de una hora 'HH:mm'. */
function minutos(hora) {
    const [h, m] = hora.split(':').map(Number);
    return h * 60 + m;
}

/**
 * Un recorrido por tour del paquete, o uno solo para un tour. Todos arrancan en
 * hoy; el selector de fecha los mueve al primer día vendible si hoy no sirve.
 */
function recorridosDe(producto) {
    const tours = producto.tours?.length ? producto.tours : [{ id: producto.id, nombre: producto.nombre }];

    return tours.map((tour) => ({
        producto_id: tour.id,
        nombre: tour.nombre,
        fecha: hoy(),
        hora: '',
        horarios: [],
        intervalo: 30,
        cargandoHorarios: false,
        avisoHorarios: '',
    }));
}

/**
 * El mismo grupo no puede estar en dos recorridos a la vez: una hora choca si
 * otro recorrido de la línea, el mismo día, sale a menos de un intervalo.
 */
export function chocaConOtro(linea, indice, inicio) {
    const propio = linea.recorridos[indice];

    return linea.recorridos.some((otro, i) =>
        i !== indice
        && otro.hora
        && otro.fecha === propio.fecha
        && Math.abs(minutos(otro.hora) - minutos(inicio)) < Math.max(otro.intervalo || 30, propio.intervalo || 30)
    );
}

/**
 * Carrito de la venta en curso.
 *
 * Una línea lleva UN nombre de cliente y UN número de participantes. Si requiere
 * reservación, lleva además una lista de `recorridos`: uno para un tour, uno por
 * cada tour de un paquete. Cada recorrido tiene su propia fecha y hora, porque un
 * paquete puede repartirse en días distintos. Al elegir la hora de uno se sugiere
 * el siguiente encadenado, si está libre, para no capturar de más en el caso común.
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

        /** Líneas con algún recorrido que todavía no tiene horario. */
        sinHorario(state) {
            return state.lineas.filter((l) => l.recorridos.some((r) => !r.hora));
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
                recorridos: producto.requiere_reservacion ? recorridosDe(producto) : [],
            };

            this.lineas.push(linea);
            linea.recorridos.forEach((_, i) => this.cargarHorarios(linea.uid, i));

            // La versión reactiva: duplicar() la modifica después de agregarla.
            return this.lineas[this.lineas.length - 1];
        },

        quitar(uid) {
            this.lineas = this.lineas.filter((l) => l.uid !== uid);
        },

        cambiarCantidad(uid, cantidad) {
            const linea = this.lineas.find((l) => l.uid === uid);
            if (!linea) return;

            // Sin tope de capacidad: en mostrador se puede pasar del cupo cuando el
            // grupo sí cabe (10 niños en un recorrido de 8). La línea solo avisa.
            linea.cantidad = Math.min(99, Math.max(1, Math.round(cantidad) || 1));
        },

        /**
         * La vista recibe las líneas de `calculadas`, que son copias: escribir el
         * nombre directo en la copia se perdía al recalcular (cambiar fecha,
         * personas o descuento) y nunca llegaba al cobro. Por eso pasa por aquí.
         */
        cambiarCliente(uid, nombre) {
            const linea = this.lineas.find((l) => l.uid === uid);
            if (linea) linea.cliente = nombre;
        },

        /**
         * Los recorridos siguientes que aún no tienen hora acompañan la fecha:
         * lo normal es que el paquete se haga el mismo día.
         */
        cambiarFecha(uid, indice, fecha) {
            const linea = this.lineas.find((l) => l.uid === uid);
            if (!linea) return;

            linea.recorridos.forEach((recorrido, i) => {
                const acompana = i > indice && !recorrido.hora;
                if ((i !== indice && !acompana) || recorrido.fecha === fecha) return;

                recorrido.fecha = fecha;
                recorrido.hora = '';
                this.cargarHorarios(uid, i);
            });
        },

        elegirHorario(uid, indice, inicio) {
            const linea = this.lineas.find((l) => l.uid === uid);
            const recorrido = linea?.recorridos[indice];
            if (!recorrido) return;

            const horario = recorrido.horarios.find((h) => h.inicio === inicio);
            if (!horario || !horario.libre || chocaConOtro(linea, indice, inicio)) return;

            recorrido.hora = inicio;
            this.sugerirSiguientes(linea, indice);
        },

        quitarHorario(uid, indice) {
            const recorrido = this.lineas.find((l) => l.uid === uid)?.recorridos[indice];
            if (recorrido) recorrido.hora = '';
        },

        /**
         * Encadena los recorridos que siguen y no tienen hora: el primer horario
         * libre del mismo día que empiece al menos un intervalo después del
         * anterior. Solo sugiere; el cajero puede cambiarlo.
         */
        sugerirSiguientes(linea, desde) {
            for (let i = desde + 1; i < linea.recorridos.length; i++) {
                const anterior = linea.recorridos[i - 1];
                const recorrido = linea.recorridos[i];

                if (recorrido.hora || !anterior.hora || recorrido.fecha !== anterior.fecha) return;

                const minimo = minutos(anterior.hora) + (anterior.intervalo || 30);
                const sugerido = recorrido.horarios.find(
                    (h) => h.libre && minutos(h.inicio) >= minimo && !chocaConOtro(linea, i, h.inicio)
                );

                if (!sugerido) return;
                recorrido.hora = sugerido.inicio;
            }
        },

        /**
         * Horarios que la sucursal tiene configurados para ese día. Vienen del
         * mismo motor que alimenta la lista de reservas y la tienda en línea, y se
         * piden por recorrido: cada tour tiene su propia ocupación.
         */
        async cargarHorarios(uid, indice) {
            const linea = this.lineas.find((l) => l.uid === uid);
            const recorrido = linea?.recorridos[indice];
            if (!recorrido?.fecha) return;

            const fecha = recorrido.fecha;
            recorrido.cargandoHorarios = true;
            recorrido.avisoHorarios = '';

            try {
                const datos = await api.get(conParametros('disponibilidad', {
                    producto_id: recorrido.producto_id,
                    fecha,
                }));

                // Si cambió la fecha mientras llegaba la respuesta, esta ya no sirve.
                if (recorrido.fecha !== fecha) return;

                recorrido.horarios = datos.horarios;
                recorrido.intervalo = datos.intervalo_minutos;

                if (!datos.abre) {
                    recorrido.avisoHorarios = 'La sucursal no abre ese día.';
                } else if (!datos.horarios.some((h) => h.libre)) {
                    recorrido.avisoHorarios = 'Ya no quedan salidas ese día.';
                }

                // Llegó después de que se eligió el anterior: sugerirlo ahora.
                if (indice > 0) this.sugerirSiguientes(linea, indice - 1);
            } catch (e) {
                recorrido.horarios = [];
                recorrido.avisoHorarios = e.listaErrores?.[0] || e.message;
            } finally {
                if (recorrido.fecha === fecha) recorrido.cargandoHorarios = false;
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

            this.ultima.lineas.forEach((guardada) => {
                const producto = useCatalogo().porId(guardada.producto_id);
                if (!producto) return;

                const linea = this.agregar(producto, guardada.cantidad);
                linea.cliente = guardada.cliente;

                // Se conservan las fechas, no las horas: esas se vuelven a elegir.
                (guardada.fechas || []).forEach((fecha, i) => {
                    const recorrido = linea.recorridos[i];
                    if (!recorrido || !fecha || recorrido.fecha === fecha) return;

                    recorrido.fecha = fecha;
                    this.cargarHorarios(linea.uid, i);
                });

                if (guardada.codigo_descuento) {
                    this.aplicarDescuento(linea.uid, guardada.codigo_descuento);
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
                        cliente: linea.cliente || null,
                        // Una reservación por recorrido, con la misma gente y el
                        // mismo nombre; cada una con su propia fecha y hora.
                        reservaciones: linea.recorridos.map((r) => ({
                            producto_id: r.producto_id,
                            nombre: linea.cliente || null,
                            personas: linea.cantidad,
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
                };

                const datos = await api.post('ventas', carga);

                // Se guarda antes de limpiar, para poder duplicarla.
                this.ultima = {
                    lineas: this.lineas.map((l) => ({
                        producto_id: l.producto.id,
                        cantidad: l.cantidad,
                        cliente: l.cliente,
                        fechas: l.recorridos.map((r) => r.fecha),
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
