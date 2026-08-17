import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

const nombreApp = import.meta.env.VITE_APP_NAME || 'Nexo';

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
    progress: {
        color: '#4f46e5',
    },
});
