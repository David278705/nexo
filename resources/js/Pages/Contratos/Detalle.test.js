import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/contratos/7",
    props: {
        usuario: {
            nombre: "Angela Lozano",
            correo: "angela@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [
                {
                    clave: "contratos",
                    etiqueta: "Contratos",
                    grupo: "Personal",
                    url: "/contratos",
                },
            ],
        },
    },
};

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    usePage: () => pagina,
    router: { get: vi.fn(), visit: vi.fn() },
    Link: {
        name: "Link",
        props: ["href"],
        template: "<a :href='href'><slot /></a>",
    },
}));

const Detalle = (await import("./Detalle.vue")).default;

const props = {
    contrato: {
        empleadoId: 7,
        empleado: "Juan Pablo Mejía Ochoa",
        cargo: "Auxiliar de Bodega",
        tipo: "Término fijo",
        estado: "Vence en 24 días",
        tono: "advertencia",
        admiteRenovacion: true,
    },
    indicadores: [
        {
            valor: "12/09/2026",
            etiqueta: "Fecha de vencimiento",
            nota: "Vence en 24 días",
            tono: "advertencia",
        },
        {
            valor: "13/08/2026",
            etiqueta: "Límite de preaviso",
            nota: "El plazo se pasó hace 6 días",
            tono: "advertencia",
        },
        { valor: "3", etiqueta: "Prórrogas acumuladas", nota: "", tono: "" },
        {
            valor: "425 días",
            etiqueta: "Duración total del vínculo",
            nota: "Tope legal de cuatro años: 15/07/2029",
            tono: "",
        },
    ],
    campos: [
        { etiqueta: "Empleado", valor: "Juan Pablo Mejía Ochoa" },
        { etiqueta: "Fecha límite de preaviso", valor: "13/08/2026" },
    ],
    historial: [
        {
            id: 1,
            orden: 1,
            evento: "Periodo inicial",
            contrato: "Término fijo",
            desde: "15/07/2025",
            hasta: "14/10/2025",
        },
        {
            id: 2,
            orden: 2,
            evento: "Prórroga pactada",
            contrato: "Término fijo",
            desde: "15/10/2025",
            hasta: "14/01/2026",
        },
        {
            id: 3,
            orden: 3,
            evento: "Prórroga automática",
            contrato: "Término fijo",
            desde: "15/01/2026",
            hasta: "14/04/2026",
        },
        {
            id: 4,
            orden: 4,
            evento: "Prórroga pactada",
            contrato: "Término fijo",
            desde: "15/04/2026",
            hasta: "12/09/2026",
        },
    ],
    cambioAIndefinido: {
        disponible: true,
        entrada:
            "Vas a cambiar el contrato de Juan Pablo Mejía Ochoa de término fijo a término indefinido.",
        titulo: "Qué implica este cambio",
        implicaciones: ["El vínculo continúa. No hay salida ni liquidación."],
        advertencia: "Este cambio no se puede revertir desde el sistema.",
    },
};

/*
 * El modal de renovación se sustituye por un maniquí: tiene su propio archivo de
 * pruebas y aquí lo que se comprueba es la pantalla que lo abre.
 */
function montar(cambios = {}) {
    return mount(Detalle, {
        props: { ...props, ...cambios },
        global: {
            stubs: { ModalDeRenovacion: true, ModalDeCambioAIndefinido: true },
        },
    });
}

describe("el detalle del contrato", function () {
    it("titula con el nombre del empleado y su estado", function () {
        const texto = montar().text();

        expect(texto).toContain("Contrato de Juan Pablo Mejía Ochoa");
        expect(texto).toContain("Vence en 24 días");
    });

    it("destaca la fecha límite de preaviso con su nota", function () {
        const texto = montar().text();

        expect(texto).toContain("Límite de preaviso");
        expect(texto).toContain("13/08/2026");
        expect(texto).toContain("El plazo se pasó hace 6 días");
    });

    it("enseña una fila de historial por cada periodo, empezando por el inicial", function () {
        const filas = montar().findAll("tbody tr");

        expect(filas).toHaveLength(4);
        expect(filas[0].text()).toContain("Periodo inicial");
        expect(filas[3].text()).toContain("12/09/2026");
    });

    it("no hace clicable el historial, que informa y no lleva a ninguna parte", function () {
        const primera = montar().find("tbody tr");

        expect(primera.classes()).not.toContain("cursor-pointer");
        expect(primera.attributes("tabindex")).toBeUndefined();
    });

    it("enlaza a la ficha del empleado", function () {
        const enlaces = montar()
            .findAll("a")
            .map((enlace) => enlace.attributes("href"));

        expect(enlaces).toContain("/empleados/7");
        expect(enlaces).toContain("/contratos");
    });

    it("ofrece renovar el contrato cuando el servidor dice que se puede", function () {
        const pantalla = montar({ renovacion: { disponible: true } });

        expect(pantalla.text()).toContain("Renovar contrato");
    });

    it("no ofrece renovar donde la acción no existe", function () {
        const pantalla = montar({
            contrato: { ...props.contrato, admiteRenovacion: false },
            renovacion: { disponible: false },
        });

        expect(pantalla.text()).not.toContain("Renovar contrato");
    });

    it("ofrece cambiar a término indefinido cuando el servidor dice que se puede", function () {
        expect(montar().text()).toContain("Cambiar a término indefinido");
    });

    it("no ofrece el cambio a indefinido donde la acción no existe", function () {
        const pantalla = montar({
            cambioAIndefinido: { disponible: false },
        });

        expect(pantalla.text()).not.toContain("Cambiar a término indefinido");
    });

    it("deja solo las tarjetas que existen en un contrato sin plazo", function () {
        const pantalla = montar({
            contrato: { ...props.contrato, tipo: "Término indefinido", estado: "Vigente", tono: "exito", admiteRenovacion: false },
            indicadores: [
                { valor: "0", etiqueta: "Prórrogas acumuladas", nota: "", tono: "" },
                {
                    valor: "15/01/2026",
                    etiqueta: "Inicio del contrato",
                    nota: "Este contrato no termina en una fecha conocida.",
                    tono: "",
                },
            ],
            cambioAIndefinido: { disponible: false },
        });

        expect(pantalla.text()).not.toContain("Límite de preaviso");
        expect(pantalla.text()).toContain("Este contrato no termina en una fecha conocida.");
    });
});
