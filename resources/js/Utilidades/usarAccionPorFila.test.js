import { readFileSync } from "node:fs";
import { beforeEach, describe, expect, it, vi } from "vitest";

const router = { post: vi.fn() };

vi.mock("@inertiajs/vue3", () => ({ router }));

const { usarAccionPorFila } = await import("./usarAccionPorFila");

/*
 * El gemelo pequeño de `useListado.test.js`: se prueba el armazón solo, sin
 * pantalla, y después se comprueba que ninguna de las cuatro pantallas del panel
 * conserve su propia copia.
 */
describe("usarAccionPorFila", () => {
    beforeEach(() => {
        router.post.mockClear();
    });

    it("no tiene ninguna fila en curso antes de disparar nada", () => {
        expect(usarAccionPorFila().activo.value).toBeNull();
    });

    it("marca la fila en curso y manda la petición con preserveScroll", () => {
        const { activo, ejecutar } = usarAccionPorFila();

        ejecutar(7, "/proveedor/catalogos/ciudades/7/estado");

        expect(activo.value).toBe(7);
        expect(router.post).toHaveBeenCalledWith(
            "/proveedor/catalogos/ciudades/7/estado",
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it("acepta datos y los manda tal cual", () => {
        usarAccionPorFila().ejecutar("uuid-1", "/reintentar", { motivo: "x" });

        expect(router.post).toHaveBeenCalledWith(
            "/reintentar",
            { motivo: "x" },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    /*
     * `onFinish` y no `onSuccess`: el indicador de carga tiene que apagarse
     * también cuando la petición falla, o la fila se queda girando para siempre.
     */
    it("suelta la fila al terminar la petición, haya ido bien o mal", () => {
        const { activo, ejecutar } = usarAccionPorFila();

        ejecutar(7, "/proveedor/catalogos/ciudades/7/estado");
        router.post.mock.calls[0][2].onFinish();

        expect(activo.value).toBeNull();
    });

    /*
     * Cada llamada devuelve su propio `ref`. Dos acciones distintas de la misma
     * pantalla —desactivar una entidad y desactivar un municipio— no pueden
     * compartir el indicador, o desactivar una encendería la ruedita de la otra.
     */
    it("cada acción lleva su propio indicador", () => {
        const entidades = usarAccionPorFila();
        const ciudades = usarAccionPorFila();

        entidades.ejecutar(3, "/entidades/3/estado");

        expect(entidades.activo.value).toBe(3);
        expect(ciudades.activo.value).toBeNull();
    });
});

describe("Las pantallas del panel no reescriben la acción por fila", () => {
    const PANTALLAS = [
        "resources/js/Pages/Proveedor/Catalogos.vue",
        "resources/js/Pages/Proveedor/Suscripciones.vue",
        "resources/js/Pages/Proveedor/FichaDeEmpresa.vue",
        "resources/js/Pages/Proveedor/Salud.vue",
    ];

    /*
     * El idioma exacto que el composable vino a borrar: el indicador de la fila
     * en curso, soltado a nulo al terminar. Se busca así de literal y no un
     * `onFinish` cualquiera porque los indicadores de un modal —«anulando»,
     * «quitandoNota»— vuelven a `false` y son otra cosa: no llevan fila.
     */
    const COPIADO = /onFinish: \(\) => \(\w+\.value = null\)/;

    it.each(PANTALLAS)("%s usa el composable y no su propia copia", (ruta) => {
        const codigo = readFileSync(ruta, "utf8");

        expect(codigo).toContain("usarAccionPorFila");
        expect(codigo).not.toMatch(COPIADO);
    });
});
