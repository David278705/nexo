import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

const nombreApp = import.meta.env.VITE_APP_NAME || 'Nexo';

function colorDeLaPaleta(variable) {
    return getComputedStyle(document.documentElement)
        .getPropertyValue(variable)
        .trim();
}

createInertiaApp({
    title: (titulo) => (titulo ? `${titulo} · ${nombreApp}` : nombreApp),
    resolve: (nombre) => {
        const paginas = import.meta.glob('./Pages/**/*.vue', { eager: true });

        return paginas[`./Pages/${nombre}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    // La barra de progreso también sale de la paleta: se lee la variable en
    // lugar de escribir el color, que es la regla del sistema de diseño.
    progress: {
        color: colorDeLaPaleta('--color-accion'),
    },
});
