import { createRouter, createWebHistory } from 'vue-router';
import { useSesion } from '../stores/sesion';

import Login from '../views/Login.vue';
import Apertura from '../views/Apertura.vue';
import Venta from '../views/Venta.vue';
import Historial from '../views/Historial.vue';
import Reservas from '../views/Reservas.vue';
import Movimientos from '../views/Movimientos.vue';
import Corte from '../views/Corte.vue';

const rutas = [
    { path: '/', redirect: '/venta' },
    { path: '/login', name: 'login', component: Login, meta: { publica: true } },
    { path: '/apertura', name: 'apertura', component: Apertura, meta: { sinCaja: true } },
    { path: '/venta', name: 'venta', component: Venta, meta: { titulo: 'Venta' } },
    { path: '/historial', name: 'historial', component: Historial, meta: { titulo: 'Historial' } },
    { path: '/reservas', name: 'reservas', component: Reservas, meta: { titulo: 'Reservas' } },
    { path: '/movimientos', name: 'movimientos', component: Movimientos, meta: { titulo: 'Movimientos' } },
    { path: '/corte', name: 'corte', component: Corte, meta: { titulo: 'Corte' } },
];

const router = createRouter({
    history: createWebHistory('/pos'),
    routes: rutas,
});

/**
 * Tres estados posibles: sin sesión, con sesión pero sin caja abierta, y
 * operando. Cada uno tiene una única pantalla válida hasta que se resuelve.
 */
router.beforeEach(async (destino) => {
    const sesion = useSesion();

    if (!sesion.inicializado) {
        await sesion.refrescar();
    }

    if (!sesion.autenticado) {
        return destino.meta.publica ? true : { name: 'login' };
    }

    if (destino.meta.publica) {
        return { name: sesion.cajaAbierta ? 'venta' : 'apertura' };
    }

    // Sin caja abierta solo se puede consultar el calendario o abrir caja.
    if (!sesion.cajaAbierta && !destino.meta.sinCaja && destino.name !== 'reservas') {
        return { name: 'apertura' };
    }

    if (sesion.cajaAbierta && destino.meta.sinCaja) {
        return { name: 'venta' };
    }

    return true;
});

export default router;
