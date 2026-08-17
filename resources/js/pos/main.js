import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import router from './router';
import { alExpirarSesion } from './api/cliente';
import { useSesion } from './stores/sesion';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Si el token vence a media operación, se corta limpio hacia el login en vez de
// dejar la pantalla mostrando errores sueltos.
alExpirarSesion(() => {
    useSesion(pinia).cerrarSesion();
    router.push({ name: 'login' });
});

app.mount('#pos');
