import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, reactive } from "vue";

const get = vi.fn();
const post = vi.fn();

/*
 * El formulario de Inertia se reemplaza por un objeto reactivo con la misma
 * forma: lo que se comprueba aquí es qué pide el modal y a dónde lo manda, no la
 * biblioteca.
 */
vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    router: { get, visit: vi.fn() },
    useForm: (valores) =>
        reactive({
            ...valores,
            errors: {},
            processing: false,
            post,
            reset: vi.fn(),
            clearErrors: vi.fn(),
        }),
}));

const ModalDeRenovacion = (await import("./ModalDeRenovacion.vue")).default;

const renovacion = {
    disponible: true,
    inicio: "01/04/2026",
    minimo: "2026-04-01",
    resumen: [
        { etiqueta: "Periodo vigente", valor: "01/01/2026 — 31/03/2026" },
        { etiqueta: "Prórrogas acumuladas", valor: "0" },
        { etiqueta: "Duración total del vínculo", valor: "90 días" },
    ],
    tipos: [
        { valor: "prorroga_pactada", texto: "Pactada por escrito" },
        { valor: "prorroga_automatica", texto: "Automática por silencio" },
    ],
    propuesta: { tipo: "", fecha_fin: "" },
    proyeccion: null,
};

function montar(cambios = {}) {
    return mount(ModalDeRenovacion, {
        props: {
            abierto: true,
            url: "/contratos/7",
            renovacion: { ...renovacion, ...cambios },
        },
        attachTo: document.body,
    });
}

describe("el modal de renovación", () => {
    beforeEach(() => {
        get.mockClear();
        post.mockClear();
        document.body.innerHTML = "";
    });

    it("explica que renovar no es corregir la fecha", () => {
        const componente = montar();

        expect(document.body.textContent).toContain(
            "Renovar es distinto de corregir la fecha",
        );

        componente.unmount();
    });

    it("enseña el periodo vigente y la duración que ya lleva", () => {
        const componente = montar();

        expect(document.body.textContent).toContain("01/01/2026 — 31/03/2026");
        expect(document.body.textContent).toContain("90 días");

        componente.unmount();
    });

    it("pide el tipo de prórroga con las dos opciones de la ley", () => {
        const componente = montar();
        const opciones = [
            ...document.body.querySelectorAll("select option"),
        ].map((opcion) => opcion.textContent.trim());

        expect(opciones).toContain("Pactada por escrito");
        expect(opciones).toContain("Automática por silencio");

        componente.unmount();
    });

    it("dice desde cuándo corre el periodo nuevo", () => {
        const componente = montar();

        expect(document.body.textContent).toContain(
            "El nuevo periodo empieza el 01/04/2026.",
        );

        componente.unmount();
    });

    it("no enseña ninguna proyección mientras el servidor no la manda", () => {
        const componente = montar();

        expect(document.body.textContent).not.toContain("Prórroga de");

        componente.unmount();
    });

    it("enseña las cuentas que llegan del servidor, sin calcularlas", () => {
        const componente = montar({
            proyeccion: {
                tono: "informacion",
                lineas: [
                    "Prórroga de 91 días, del 01/04/2026 al 30/06/2026.",
                    "El vínculo quedaría en 181 días de los cuatro años permitidos.",
                    "Sería la 1ª prórroga del contrato, prórroga pactada.",
                ],
            },
        });

        expect(document.body.textContent).toContain("Prórroga de 91 días");
        expect(document.body.textContent).toContain("El vínculo quedaría en 181 días");
        expect(document.body.textContent).toContain("Sería la 1ª prórroga");

        componente.unmount();
    });

    it("pide al servidor la proyección cuando cambia la fecha", async () => {
        const componente = montar();

        // El modal viaja a `body` por el `Teleport` del componente `Modal`, así
        // que se busca en el documento y no en el envoltorio de la prueba.
        const campo = document.body.querySelector('input[type="date"]');
        campo.value = "2026-06-30";
        campo.dispatchEvent(new Event("input"));

        await nextTick();

        expect(get).toHaveBeenCalledTimes(1);
        expect(get.mock.calls[0][0]).toBe("/contratos/7");
        expect(get.mock.calls[0][1]).toEqual({
            tipo: "",
            fecha_fin: "2026-06-30",
        });
        expect(get.mock.calls[0][2].only).toEqual(["renovacion"]);

        componente.unmount();
    });

    it("deshabilita el registro cuando el servidor dice que está bloqueado", async () => {
        const componente = montar({
            proyeccion: {
                tono: "error",
                bloqueado: true,
                lineas: [
                    "No se puede registrar esta prórroga: con esta fecha el vínculo a término fijo superaría el tope legal de cuatro años.",
                ],
            },
        });

        const registrar = [...document.body.querySelectorAll("button")].at(-1);

        expect(document.body.textContent).toContain(
            "No se puede registrar esta prórroga",
        );
        expect(registrar.disabled).toBe(true);

        registrar.dispatchEvent(new Event("click"));
        await nextTick();

        expect(post).not.toHaveBeenCalled();

        componente.unmount();
    });

    it("deja registrar cuando la proyección solo advierte", () => {
        const componente = montar({
            proyeccion: {
                tono: "advertencia",
                bloqueado: false,
                lineas: ["A partir de esta renovación el contrato no puede prorrogarse por menos de un año."],
            },
        });

        expect(
            [...document.body.querySelectorAll("button")].at(-1).disabled,
        ).toBe(false);

        componente.unmount();
    });

    it("envía la prórroga a la dirección de renovar", async () => {
        const componente = montar();

        const botones = [...document.body.querySelectorAll("button")];
        botones.at(-1).dispatchEvent(new Event("click"));

        await nextTick();

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe("/contratos/7/renovar");

        componente.unmount();
    });
});
