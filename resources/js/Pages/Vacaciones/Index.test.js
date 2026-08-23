import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const reload = vi.fn();

/*
 * La advertencia de saldo insuficiente (F2-06): la franja permanente de
 * `saldoDeVacaciones` y el pedido de esa prop al servidor son la única
 * lógica propia de esta pantalla. El resto —filtros, listado, exportar— ya
 * lo prueba `useListado.test.js`.
 */
const empleadoDeNovedad = {
    documento: "43887201",
    encontrado: true,
    error: null,
    vinculacion: 7,
    nombre: "Carolina Ramírez Gómez",
    datos: [
        { etiqueta: "Empleado", valor: "Carolina Ramírez Gómez", pendiente: false, nota: "" },
    ],
};

const pagina = {
    url: "/vacaciones",
    props: {
        usuario: {
            nombre: "Angela Lozano",
            correo: "angela@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [
                { clave: "vacaciones", etiqueta: "Vacaciones", grupo: "Novedades", url: "/vacaciones" },
            ],
        },
        flujoDeNovedad: {
            etiqueta: "Número de documento del empleado",
            ayuda: "El empleado debe estar registrado y activo.",
            tipos: [
                { clave: "vacaciones", titulo: "Registrar vacaciones", modulo: "vacaciones" },
            ],
        },
        empleadoDeNovedad,
        estado: "",
    },
};

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    usePage: () => pagina,
    router: { reload, get: vi.fn(), visit: vi.fn() },
}));

const Index = (await import("./Index.vue")).default;

function montar(props = {}) {
    return mount(Index, {
        props: {
            vacaciones: { filas: [], pagina: 1, paginas: 1, total: 0, desde: null, hasta: null },
            filtros: {
                buscar: "",
                desde: "",
                hasta: "",
                cargo: "",
                orden: "inicio",
                direccion: "desc",
                porPagina: 25,
            },
            aplicados: [],
            tieneFiltros: false,
            opciones: { cargos: ["Analista de compras"], tamanos: [25, 50, 100] },
            diasDeVacaciones: null,
            saldoDeVacaciones: null,
            ...props,
        },
        attachTo: document.body,
    });
}

function boton(texto) {
    return [...document.body.querySelectorAll("button")].find(
        (candidato) => candidato.textContent.trim() === texto,
    );
}

/**
 * El valor de un `CampoTexto` de solo lectura, que no queda en el texto del
 * modal porque vive en el `value` del `<input>`, no en un nodo de texto.
 */
function valorDelCampo(etiqueta) {
    const label = [...document.body.querySelectorAll("label")].find(
        (candidato) => candidato.textContent.trim().startsWith(etiqueta),
    );

    return document.getElementById(label.getAttribute("for")).value;
}

/**
 * Abre el modal y deja seleccionado al empleado de prueba, que es el punto
 * donde `Vacaciones/Index.vue` empieza a pedir la franja de saldo.
 */
async function abrirYSeleccionar() {
    boton("Registrar vacaciones").dispatchEvent(new Event("click"));
    await nextTick();

    const campo = document.body.querySelector('[role="dialog"] input');
    campo.value = "43887201";
    campo.dispatchEvent(new Event("input"));
    await nextTick();
    campo.dispatchEvent(new Event("blur"));
    await nextTick();

    boton("Continuar").dispatchEvent(new Event("click"));
    await nextTick();
}

describe("la franja de saldo de vacaciones (F2-06)", () => {
    beforeEach(() => {
        reload.mockClear();
        document.body.innerHTML = "";
    });

    it("pide la franja de saldo en cuanto se resuelve el empleado, antes de tener fechas", async () => {
        const componente = montar();

        await abrirYSeleccionar();

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ["diasDeVacaciones", "saldoDeVacaciones"],
                data: { vinculacion_id: 7, fecha_inicio: "", fecha_fin: "" },
            }),
        );

        componente.unmount();
    });

    it("no pide nada mientras no se haya resuelto ningún empleado", () => {
        montar();

        expect(reload).not.toHaveBeenCalled();
    });

    it("no enseña la franja mientras el servidor no la haya contestado", async () => {
        const componente = montar();

        await abrirYSeleccionar();

        expect(document.body.textContent).not.toContain("Saldo actual");

        componente.unmount();
    });

    it("enseña el saldo actual y proyectado que llegan del servidor", async () => {
        const componente = montar();

        await abrirYSeleccionar();

        await componente.setProps({
            saldoDeVacaciones: {
                saldoActual: "12,75 días",
                saldoProyectado: null,
                advertencia: null,
            },
        });

        expect(document.body.textContent).toContain("Saldo actual");
        expect(valorDelCampo("Saldo actual")).toBe("12,75 días");
        expect(document.body.textContent).toContain(
            "Saldo proyectado a la fecha de disfrute",
        );
        // Sin fechas todavía no hay proyección, y la franja no inventa un número.
        expect(valorDelCampo("Saldo proyectado a la fecha de disfrute")).toBe(
            "—",
        );

        componente.unmount();
    });

    it("muestra la advertencia ámbar exacta del documento 3 cuando los días no alcanzan", async () => {
        const componente = montar();

        await abrirYSeleccionar();

        await componente.setProps({
            saldoDeVacaciones: {
                saldoActual: "12,75 días",
                saldoProyectado: "12,75 días",
                advertencia:
                    "Al 28 de agosto tendrá 12,75 días disponibles. Estás registrando 14 días: faltan 1,25 días.",
            },
        });

        expect(document.body.textContent).toContain(
            "Al 28 de agosto tendrá 12,75 días disponibles. Estás registrando 14 días: faltan 1,25 días.",
        );

        componente.unmount();
    });

    it("no bloquea el guardado cuando hay advertencia de saldo insuficiente", async () => {
        const componente = montar({ diasDeVacaciones: "14 días" });

        await abrirYSeleccionar();

        await componente.setProps({
            saldoDeVacaciones: {
                saldoActual: "12,75 días",
                saldoProyectado: "12,75 días",
                advertencia:
                    "Al 28 de agosto tendrá 12,75 días disponibles. Estás registrando 14 días: faltan 1,25 días.",
            },
        });

        expect(boton("Guardar").disabled).toBe(false);

        componente.unmount();
    });
});
