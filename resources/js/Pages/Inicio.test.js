import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

// `Head` de Inertia necesita el gestor de encabezado que instala `createInertiaApp`,
// que no existe fuera del navegador. Se reemplaza por un componente vacío.
vi.mock('@inertiajs/vue3', async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: 'Head', template: '<span />' },
}));

const Inicio = (await import('./Inicio.vue')).default;

describe('Inicio', () => {
    it('muestra el mensaje que llega como prop', () => {
        const componente = mount(Inicio, {
            props: { mensaje: 'Hola' },
        });

        expect(componente.text()).toContain('Hola');
    });
});
