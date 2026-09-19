import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, reactive } from "vue";

const get = vi.fn();
const post = vi.fn();

/*
 * El formulario de Inertia se reemplaza por un objeto reactivo con la misma
 * forma: lo que se comprueba aquí es qué pide el modal y a dónde manda la
 * confirmación, no la biblioteca.
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
            clearErrors: vi.fn(),
        }),
}));

const ModalDeCambioAIndefinido = (
    await import("./ModalDeCambioAIndefinido.vue")
).default;

const cambio = {
    disponible: true,
    entrada:
        "Vas a cambiar el contrato de Juan Pablo Mejía de término fijo a término indefinido.",
    titulo: "Qué implica este cambio",
    implicaciones: [
        "El vínculo continúa. No hay salida ni liquidación.",
        "La fecha de ingreso no cambia: sigue siendo el 15/07/2025.",
        "La antigüedad y el saldo de vacaciones siguen corriendo desde esa fecha.",
        "Dejarán de generarse las alertas de vencimiento y renovación.",
        "El cambio queda registrado en el historial con fecha del 20/08/2026 y en la auditoría.",
    ],
    advertencia:
        "Este cambio no se puede revertir desde el sistema. Un contrato indefinido no puede volver a ser a término fijo.",
    fecha: "2026-08-20",
    minimo: "2025-07-15",
    maximo: "2026-08-20",
};

function montar(cambios = {}) {
    return mount(ModalDeCambioAIndefinido, {
        props: {
            abierto: true,
            url: "/contratos/7",
            cambio: { ...cambio, ...cambios },
        },
        attachTo: document.body,
    });
}

describe("el modal de cambio a término indefinido", () => {
    beforeEach(() => {
        get.mockClear();
        post.mockClear();
        document.body.innerHTML = "";
    });

    it("dice a quién se le cambia el contrato y a qué", () => {
        const componente = montar();

        expect(document.body.textContent).toContain(
            "Vas a cambiar el contrato de Juan Pablo Mejía",
        );

        componente.unmount();
    });

    it("enseña las cinco consecuencias que llegan del servidor, sin escribir ninguna", () => {
        const componente = montar();
        const puntos = [...document.body.querySelectorAll("li")].map((punto) =>
            punto.textContent.trim(),
        );

        expect(puntos).toEqual(cambio.implicaciones);

        componente.unmount();
    });

    it("advierte que el cambio no se puede revertir", () => {
        const componente = montar();

        expect(document.body.textContent).toContain(
            "Este cambio no se puede revertir desde el sistema",
        );

        componente.unmount();
    });

    it("pide la fecha del cambio, propuesta en la que llega del servidor", () => {
        const componente = montar();
        const campo = document.body.querySelector('input[type="date"]');

        expect(campo).not.toBeNull();
        expect(campo.value).toBe("2026-08-20");

        componente.unmount();
    });

    it("pide al servidor las implicaciones de nuevo cuando cambia la fecha", async () => {
        const componente = montar();

        // El modal viaja a `body` por el `Teleport` del componente `Modal`, así
        // que se busca en el documento y no en el envoltorio de la prueba.
        const campo = document.body.querySelector('input[type="date"]');
        campo.value = "2026-08-10";
        campo.dispatchEvent(new Event("input"));

        await nextTick();

        expect(get).toHaveBeenCalledTimes(1);
        expect(get.mock.calls[0][0]).toBe("/contratos/7");
        expect(get.mock.calls[0][1]).toEqual({ fecha: "2026-08-10" });
        expect(get.mock.calls[0][2].only).toEqual(["cambioAIndefinido"]);

        componente.unmount();
    });

    it("confirma contra la dirección de la acción", () => {
        const componente = montar();
        const boton = [...document.body.querySelectorAll("button")].find(
            (candidato) => candidato.textContent.includes("Confirmar cambio"),
        );

        boton.click();

        expect(post).toHaveBeenCalledWith(
            "/contratos/7/indefinido",
            expect.anything(),
        );

        componente.unmount();
    });

    it("cierra sin confirmar cuando se cancela", async () => {
        const componente = montar();
        const boton = [...document.body.querySelectorAll("button")].find(
            (candidato) => candidato.textContent.trim() === "Cancelar",
        );

        boton.click();

        expect(post).not.toHaveBeenCalled();
        expect(componente.emitted("cerrar")).toHaveLength(1);

        componente.unmount();
    });
});
