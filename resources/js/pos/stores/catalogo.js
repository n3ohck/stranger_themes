import { defineStore } from 'pinia';
import { api } from '../api/cliente';

/**
 * Catálogo de la sucursal. Se carga una vez por sesión y se mantiene en memoria:
 * el cajero cambia de pestaña constantemente y no tiene sentido volver a pedirlo.
 */
export const useCatalogo = defineStore('catalogo', {
    state: () => ({
        productos: [],
        descuentos: [],
        cargado: false,
        cargando: false,
    }),

    getters: {
        porId: (state) => (id) => state.productos.find((p) => p.id === id),

        /** Pestañas del catálogo, en el orden en que se usan en mostrador. */
        grupos: (state) => [
            { clave: 'todos', etiqueta: 'Todos', tipos: null },
            { clave: 'tours', etiqueta: 'Tours', tipos: ['tour'] },
            { clave: 'paquetes', etiqueta: 'Paquetes', tipos: ['tour_paquete'] },
            { clave: 'productos', etiqueta: 'Productos', tipos: ['articulo'] },
            { clave: 'diferencias', etiqueta: 'Diferencias', tipos: ['diferencias'] },
        ].filter((grupo) => !grupo.tipos || state.productos.some((p) => grupo.tipos.includes(p.tipo))),

        delGrupo: (state) => (tipos) => {
            if (!tipos) return state.productos;
            return state.productos.filter((p) => tipos.includes(p.tipo));
        },
    },

    actions: {
        async cargar(forzar = false) {
            if (this.cargado && !forzar) return;

            this.cargando = true;

            try {
                const datos = await api.get('catalogo');
                this.productos = datos.productos;
                this.descuentos = datos.descuentos;
                this.cargado = true;
            } finally {
                this.cargando = false;
            }
        },

        /**
         * Valida un código contra la copia local para dar retroalimentación
         * inmediata. El servidor vuelve a validarlo al cobrar; esto es solo para
         * no hacer esperar al cajero.
         */
        buscarDescuento(codigo) {
            if (!codigo) return null;

            const limpio = String(codigo).trim().toLowerCase();

            return this.descuentos.find((d) => d.codigo.toLowerCase() === limpio) || null;
        },
    },
});
