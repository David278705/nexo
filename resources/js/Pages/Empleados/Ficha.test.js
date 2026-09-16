import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/empleados/7",
    props: {
        usuario: {
            nombre: "Angela Lozano",
            correo: "angela@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [
                {
                    clave: "empleados",
                    etiqueta: "Empleados",
                    grupo: "Personal",
                    url: "/empleados",
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

const Ficha = (await import("./Ficha.vue")).default;

const props = {
    empleado: {
        id: 7,
        nombre: "Carolina Restrepo Vélez",
        cargo: "Coordinadora de Operaciones",
        estado: "Activo",
        activo: true,
    },
    resumen: [
        { etiqueta: "Documento", valor: "43887201", pendiente: false, nota: "" },
        {
            etiqueta: "Fecha de ingreso",
            valor: "03/02/2022",
            pendiente: false,
            nota: "",
        },
        {
            etiqueta: "Antigüedad",
            valor: "4 años, 6 meses",
            pendiente: false,
            nota: "",
        },
        {
            etiqueta: "Tipo de contrato",
            valor: "Término indefinido",
            pendiente: false,
            nota: "",
        },
        {
            etiqueta: "Salario",
            valor: "$ 3.200.000",
            pendiente: false,
            nota: "",
        },
        {
            etiqueta: "Saldo de vacaciones",
            valor: "Aún no disponible",
            pendiente: true,
            nota: "El saldo se calcula con las vacaciones disfrutadas.",
        },
    ],
    datos: [
        {
            titulo: "Identificación",
            campos: [
                { etiqueta: "Tipo de documento", valor: "Cédula de ciudadanía" },
                { etiqueta: "Género", valor: "—" },
            ],
            vacio: "",
        },
        {
            titulo: "Seguridad social",
            campos: [{ etiqueta: "EPS", valor: "Sura EPS" }],
            vacio: "",
        },
    ],
    contrato: {
        campos: [{ etiqueta: "Tipo de contrato", valor: "Término indefinido" }],
        historial: [
            {
                id: 1,
                orden: 1,
                evento: "Contrato inicial",
                contrato: "Término indefinido",
                desde: "03/02/2022",
                hasta: "—",
            },
        ],
    },
    novedades: [],
    movimientos: [],
};

// Las tres clases juntas y ya ordenadas por el servidor, tal como llegan de
// `FichaDeEmpleado::novedades()`.
const novedades = [
    {
        id: "vacacion-4",
        clase: "Vacaciones",
        desde: "27/07/2026",
        hasta: "31/07/2026",
        dias: 5,
        detalle: "—",
    },
    {
        id: "incapacidad-9",
        clase: "Incapacidad",
        desde: "14/07/2026",
        hasta: "16/07/2026",
        dias: 3,
        detalle: "Común · Diagnóstico J06.9",
    },
    {
        id: "licencia-2",
        clase: "Suspensión",
        desde: "01/06/2026",
        hasta: "03/06/2026",
        dias: 3,
        detalle: "—",
    },
];

function montar(cambios = {}) {
    return mount(Ficha, { props: { ...props, ...cambios } });
}

describe("la ficha del empleado", () => {
    it("enseña el nombre, el cargo y el estado en el encabezado", () => {
        const texto = montar().text();

        expect(texto).toContain("Carolina Restrepo Vélez");
        expect(texto).toContain("Coordinadora de Operaciones");
        expect(texto).toContain("Activo");
    });

    it("deja el saldo de vacaciones siempre visible, en cualquier pestaña", async () => {
        const pantalla = montar();

        for (const clave of ["datos", "contrato", "novedades", "movimientos"]) {
            await pantalla
                .find(`[aria-controls='panel-${clave}']`)
                .trigger("click");

            expect(pantalla.text()).toContain("Saldo de vacaciones");
            expect(pantalla.text()).toContain("Aún no disponible");
        }
    });

    it("abre en datos personales y enseña sus bloques", () => {
        const pantalla = montar();

        expect(pantalla.text()).toContain("Identificación");
        expect(pantalla.text()).toContain("Cédula de ciudadanía");
        expect(pantalla.text()).toContain("Sura EPS");
    });

    it("tiene las cuatro pestañas del documento 3", () => {
        const titulos = montar()
            .findAll("[role='tab']")
            .map((boton) => boton.text());

        expect(titulos).toEqual([
            "Datos personales",
            "Contrato",
            "Novedades",
            "Movimientos",
        ]);
    });

    it("carga el historial del contrato al pasar a esa pestaña", async () => {
        const pantalla = montar();

        await pantalla
            .find("[aria-controls='panel-contrato']")
            .trigger("click");

        expect(pantalla.text()).toContain("Historial de periodos");
        expect(pantalla.text()).toContain("Contrato inicial");
    });

    it("explica qué se verá en novedades en vez de decir «No hay datos»", async () => {
        const pantalla = montar();

        await pantalla
            .find("[aria-controls='panel-novedades']")
            .trigger("click");

        expect(pantalla.text()).not.toContain("No hay datos");
        expect(pantalla.text()).toContain("incapacidades, vacaciones y licencias");
    });

    it("enseña las tres clases de novedad juntas en una sola tabla", async () => {
        const pantalla = montar({ novedades });

        await pantalla
            .find("[aria-controls='panel-novedades']")
            .trigger("click");

        const texto = pantalla.text();

        expect(texto).toContain("Vacaciones");
        expect(texto).toContain("Incapacidad");
        expect(texto).toContain("Suspensión");
        expect(texto).toContain("Común · Diagnóstico J06.9");
    });

    /*
     * Los días de vacaciones son hábiles y los de las otras dos corridos. La
     * columna es una sola, así que la pantalla dice cuál es cuál: sin la nota,
     * la única lectura posible sería sumarlos.
     */
    it("advierte que los días de la tabla se cuentan con dos reglas distintas", async () => {
        const pantalla = montar({ novedades });

        await pantalla
            .find("[aria-controls='panel-novedades']")
            .trigger("click");

        expect(pantalla.text()).toContain("vacaciones son hábiles");
        expect(pantalla.text()).toContain("corridos");
    });

    it("no escribe la nota de los días cuando no hay ninguna novedad", async () => {
        const pantalla = montar();

        await pantalla
            .find("[aria-controls='panel-novedades']")
            .trigger("click");

        expect(pantalla.text()).not.toContain("vacaciones son hábiles");
    });

    it("explica qué se verá en movimientos", async () => {
        const pantalla = montar();

        await pantalla
            .find("[aria-controls='panel-movimientos']")
            .trigger("click");

        expect(pantalla.text()).toContain("aumentos de salario");
    });

    it("marca cuál es la pestaña activa para el lector de pantalla", async () => {
        const pantalla = montar();

        expect(
            pantalla.find("[aria-controls='panel-datos']").attributes("aria-selected"),
        ).toBe("true");

        await pantalla
            .find("[aria-controls='panel-contrato']")
            .trigger("click");

        expect(
            pantalla.find("[aria-controls='panel-datos']").attributes("aria-selected"),
        ).toBe("false");
        expect(
            pantalla
                .find("[aria-controls='panel-contrato']")
                .attributes("aria-selected"),
        ).toBe("true");
    });

    it("se mueve entre pestañas con las flechas del teclado", async () => {
        const pantalla = montar();

        await pantalla
            .find("[aria-controls='panel-datos']")
            .trigger("keydown", { key: "ArrowRight" });

        expect(
            pantalla
                .find("[aria-controls='panel-contrato']")
                .attributes("aria-selected"),
        ).toBe("true");
    });

    it("enseña el perfil sociodemográfico sin diligenciar con su explicación", () => {
        const pantalla = montar({
            datos: [
                ...props.datos,
                {
                    titulo: "Perfil sociodemográfico",
                    campos: [],
                    vacio: "Esta persona todavía no tiene diligenciado el perfil sociodemográfico.",
                },
            ],
        });

        expect(pantalla.text()).toContain("Sin diligenciar");
        expect(pantalla.text()).toContain(
            "no tiene diligenciado el perfil sociodemográfico",
        );
    });

    it("vuelve al listado de empleados", () => {
        expect(montar().find("a[href='/empleados']").exists()).toBe(true);
    });
});
