import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';

import App from './App.vue';
import Comprar from './views/Comprar.vue';
import Gracias from './views/Gracias.vue';

/*
 * Rutas completas desde la raíz y no con base '/comprar': con base, la pantalla
 * principal quedaba como /comprar/ (con diagonal final) y al recargarla en
 * Hostinger el servidor redirigía a /comprar/public/comprar.
 *
 * El comodín final rescata cualquier URL desconocida, incluida esa misma, que
 * los navegadores ya guardaron como redirección permanente.
 */
const router = createRouter({
    history: createWebHistory('/'),
    routes: [
        { path: '/comprar', name: 'comprar', component: Comprar },
        { path: '/comprar/gracias/:referencia', name: 'gracias', component: Gracias, props: true },
        { path: '/:pathMatch(.*)*', redirect: { name: 'comprar' } },
    ],
    scrollBehavior: () => ({ top: 0 }),
});

createApp(App).use(router).mount('#tienda');
