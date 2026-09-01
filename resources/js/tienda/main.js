import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';

import App from './App.vue';
import Comprar from './views/Comprar.vue';
import Gracias from './views/Gracias.vue';

const router = createRouter({
    history: createWebHistory('/comprar'),
    routes: [
        { path: '/', name: 'comprar', component: Comprar },
        { path: '/gracias/:referencia', name: 'gracias', component: Gracias, props: true },
    ],
    scrollBehavior: () => ({ top: 0 }),
});

createApp(App).use(router).mount('#tienda');
