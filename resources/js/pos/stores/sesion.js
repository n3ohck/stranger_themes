import { defineStore } from 'pinia';
import { api, guardarToken, borrarToken, leerToken } from '../api/cliente';

/**
 * Sesión del cajero y estado de la caja.
 *
 * La verdad vive en el servidor: al arrancar (y tras cada operación de caja) se
 * consulta /session. El token guardado en localStorage solo sirve para no pedir
 * la contraseña en cada recarga; si el servidor dice que no hay caja abierta,
 * no la hay, aunque el navegador crea otra cosa.
 */
export const useSesion = defineStore('sesion', {
    state: () => ({
        usuario: null,
        sucursal: null,
        apertura: null,
        // cargando cubre solo el arranque; inicializado evita que un refresco
        // posterior desmonte la pantalla en la que está trabajando el cajero.
        cargando: true,
        inicializado: false,
        error: null,
    }),

    getters: {
        autenticado: (state) => !!state.usuario,
        cajaAbierta: (state) => !!state.apertura,
    },

    actions: {
        async iniciarSesion(cuenta, contrasena) {
            this.error = null;

            const datos = await api.post('login', { account: cuenta, password: contrasena }, { ignorar401: true });

            guardarToken(datos.token);
            this.usuario = datos.usuario;
            this.sucursal = datos.usuario.sucursal;

            await this.refrescar();
        },

        /**
         * Reconstruye el estado desde el servidor. Se llama al montar la app y
         * después de abrir o cerrar caja.
         */
        async refrescar() {
            if (!leerToken()) {
                this.usuario = null;
                this.apertura = null;
                this.cargando = false;
                this.inicializado = true;
                return;
            }

            try {
                const datos = await api.get('session', { ignorar401: true });
                this.usuario = datos.usuario;
                this.sucursal = datos.sucursal;
                this.apertura = datos.apertura;
            } catch (e) {
                // Token vencido o revocado: se cierra la sesión en silencio y el
                // guard del router manda al login.
                this.usuario = null;
                this.apertura = null;
                borrarToken();
            } finally {
                this.cargando = false;
                this.inicializado = true;
            }
        },

        async abrirCaja(montoApertura) {
            const datos = await api.post('caja/apertura', { monto_apertura: montoApertura });
            this.apertura = datos.apertura;
            return datos;
        },

        cerrarSesion() {
            borrarToken();
            this.usuario = null;
            this.sucursal = null;
            this.apertura = null;
        },
    },
});
