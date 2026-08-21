import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/proveedor/catalogos",
    props: {
        usuario: { nombre: "David Lozano", correo: "david@nexo.co", modulos: [] },
        secciones: [
            {
                clave: "catalogos",
                etiqueta: "Catálogos",
                grupo: "Sistema",
                url: "/proveedor/catalogos",
            },
        ],
    },
};

const pedir = vi.fn();
const enviar = vi.fn();
const borrar = vi.fn();

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    usePage: () => pagina,
    router: {
        get: (...argumentos) => pedir(...argumentos),
        post: (...argumentos) => enviar(...argumentos),
        delete: (...argumentos) => borrar(...argumentos),
        visit: vi.fn(),
    },
    Link: {
        name: "Link",
        props: ["href"],
        template: "<a :href='href'><slot /></a>",
    },
}));

const Catalogos = (await import("./Catalogos.vue")).default;

const base = {
    pestanas: [
        { clave: "constantes", titulo: "Constantes legales" },
        { clave: "festivos", titulo: "Festivos" },
        { clave: "entidades", titulo: "EPS y entidades" },
        { clave: "ciudades", titulo: "Ciudades" },
        { clave: "umbrales", titulo: "Umbrales" },
    ],
    viendo: {
        pestana: "constantes",
        entidad: "eps",
        departamento: 5,
        anio: 2027,
    },
    aviso: null,
    constantes: {
        filas: [
            {
                constante: "Acumulación de vacaciones por mes",
                valor: "1,25 días",
                norma: "Art. 186 CST — 15 días hábiles por año",
                verificada: "17/08/2026",
            },
        ],
        nota: "Estos valores se leen de la configuración del sistema y no se editan desde aquí.",
    },
    festivos: {
        filas: [
            {
                id: 4,
                fecha: "20/07/2027",
                dia: "Martes, 20 de julio de 2027",
                nombre: "Día de la Independencia",
                enSabado: false,
            },
            {
                id: 5,
                fecha: "02/01/2027",
                dia: "Sábado, 2 de enero de 2027",
                nombre: "Año Nuevo trasladado",
                enSabado: true,
            },
        ],
        anios: [2026, 2027, 2028],
    },
    entidades: {
        listas: [
            { valor: "eps", texto: "EPS" },
            { valor: "bancos", texto: "Bancos" },
        ],
        filas: [
            {
                id: 9,
                codigo: "EPS037",
                codigoValor: "EPS037",
                nombre: "Nueva EPS",
                activo: true,
                estado: "Se ofrece",
                tono: "exito",
            },
            {
                id: 10,
                codigo: "—",
                codigoValor: "",
                nombre: "Coomeva",
                activo: false,
                estado: "Desactivada",
                tono: "neutro",
            },
        ],
        codigo: "Código de la Supersalud",
    },
    ciudades: {
        departamentos: [{ valor: 5, texto: "Antioquia" }],
        filas: [
            {
                id: 21,
                codigo: "05001",
                codigoValor: "05001",
                nombre: "Medellín",
                activo: true,
                estado: "Se ofrece",
                tono: "exito",
            },
        ],
    },
    umbrales: {
        filas: [
            {
                umbral: "Días de mora antes de suspender a un cliente",
                valor: "Sin decidir",
                donde: "Llega con las suscripciones (F5-04)",
                estado: "La escalera del capítulo 6 propone 5, 15 y 30 días",
                tono: "neutro",
            },
        ],
        nota: "Estos números no vienen de la ley: son decisiones del negocio.",
    },
};

function montar(pestana = "constantes") {
    return mount(Catalogos, {
        props: { ...base, viendo: { ...base.viendo, pestana } },
    });
}

function boton(pantalla, texto) {
    return pantalla
        .findAll("button")
        .find((uno) => uno.text().includes(texto));
}

describe("la pantalla de catálogos", () => {
    it("abre en las constantes legales, con su norma y sin forma de editarlas", () => {
        const pantalla = montar();

        expect(pantalla.findAll("[role='tab']")).toHaveLength(5);
        expect(pantalla.text()).toContain("Art. 186 CST");
        expect(pantalla.text()).toContain("no se editan desde aquí");
        expect(boton(pantalla, "Editar")).toBeUndefined();
    });

    it("pide la pestaña al servidor en vez de esconder y mostrar", async () => {
        const pantalla = montar();

        const festivos = pantalla
            .findAll("[role='tab']")
            .find((uno) => uno.text() === "Festivos");

        await festivos.trigger("click");

        expect(pedir).toHaveBeenCalledWith(
            "/proveedor/catalogos",
            expect.objectContaining({ pestana: "festivos" }),
            expect.anything(),
        );
    });

    it("marca los festivos que caen en sábado", () => {
        const pantalla = montar("festivos");

        expect(pantalla.text()).toContain("En sábado");
    });

    it("explica la consecuencia antes de quitar un festivo", async () => {
        const pantalla = montar("festivos");

        await boton(pantalla, "Quitar").trigger("click");

        expect(document.body.textContent).toContain("vuelve a contar como hábil");
        expect(document.body.textContent).toContain("No se borra de verdad");
    });

    it("ofrece desactivar la entidad activa y activar la desactivada", () => {
        const pantalla = montar("entidades");

        expect(pantalla.text()).toContain("Desactivar");
        expect(pantalla.text()).toContain("Activar");
        expect(pantalla.text()).toContain("Código de la Supersalud");
    });

    it("desactiva sin borrar", async () => {
        const pantalla = montar("entidades");

        await boton(pantalla, "Desactivar").trigger("click");

        expect(enviar).toHaveBeenCalledWith(
            "/proveedor/catalogos/entidades/eps/9/estado",
            {},
            expect.anything(),
        );
    });

    it("dice de qué decisión depende cada umbral que todavía no existe", () => {
        const pantalla = montar("umbrales");

        expect(pantalla.text()).toContain("Sin decidir");
        expect(pantalla.text()).toContain("Llega con las suscripciones (F5-04)");
    });
});
