import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/proveedor/puesta-en-marcha",
    props: {
        usuario: { nombre: "David Lozano", correo: "david@nexo.co", modulos: [] },
        secciones: [
            {
                clave: "puesta-en-marcha",
                etiqueta: "Puesta en marcha",
                grupo: "Clientes",
                url: "/proveedor/puesta-en-marcha",
            },
        ],
    },
};

const pedir = vi.fn();
const enviar = vi.fn();

/*
 * `useForm` se reemplaza por un objeto reactivo con lo que esta pantalla usa.
 * El de verdad no manda por el `router` de este módulo sino por el del núcleo de
 * Inertia, así que sin esto no hay forma de ver a qué dirección se envía, que es
 * justo la diferencia que importa: validar no escribe y cargar sí.
 */
vi.mock("@inertiajs/vue3", async (importarOriginal) => {
    const { reactive } = await import("vue");

    return {
        ...(await importarOriginal()),
        Head: { name: "Head", template: "<span />" },
        usePage: () => pagina,
        useForm: (datos) =>
            reactive({
                ...datos,
                errors: {},
                processing: false,
                post: (...argumentos) => enviar(...argumentos),
            }),
        router: {
            get: (...argumentos) => pedir(...argumentos),
            post: (...argumentos) => enviar(...argumentos),
            visit: vi.fn(),
        },
    };
});

const PuestaEnMarcha = (await import("./PuestaEnMarcha.vue")).default;

const PLANTILLAS = [
    { valor: "empleados", texto: "Empleados actuales" },
    { valor: "perfil", texto: "Perfil sociodemográfico" },
    { valor: "vacaciones", texto: "Histórico de vacaciones" },
];

const base = {
    empresas: [{ valor: 7, texto: "Distribuidora El Progreso S.A.S." }],
    plantillas: PLANTILLAS,
    viendo: { empresa: 7, plantilla: "empleados" },
    estadoDeLaEmpresa: {
        empresa: "Distribuidora El Progreso S.A.S.",
        plantaCargada: false,
        plantillas: [
            {
                clave: "empleados",
                nombre: "Empleados actuales",
                obligatoria: true,
                cargada: null,
            },
            {
                clave: "perfil",
                nombre: "Perfil sociodemográfico",
                obligatoria: false,
                cargada: null,
            },
            {
                clave: "vacaciones",
                nombre: "Histórico de vacaciones",
                obligatoria: false,
                cargada: null,
            },
        ],
    },
    historial: [],
    informe: null,
    notaDeLaTransaccion:
        "La carga se ejecuta dentro de una transacción: o entra todo o no entra nada.",
    notaDelOrden:
        "Primero la plantilla de empleados y solo después los históricos.",
};

const informeLimpio = {
    plantilla: "Empleados actuales",
    filasLeidas: 32,
    filasCorrectas: 32,
    filasConError: 0,
    errores: [],
};

const informeConErrores = {
    plantilla: "Empleados actuales",
    filasLeidas: 34,
    filasCorrectas: 31,
    filasConError: 3,
    errores: [
        {
            fila: 7,
            columna: "EPS",
            motivo: "«Sanitaz» no está en el catálogo.",
            texto: "Fila 7, «EPS»: «Sanitaz» no está en el catálogo.",
        },
        {
            fila: 19,
            columna: null,
            motivo: "El documento 43887201 está repetido en el archivo.",
            texto: "Fila 19: El documento 43887201 está repetido en el archivo.",
        },
    ],
};

function montar(cambios = {}) {
    return mount(PuestaEnMarcha, { props: { ...base, ...cambios } });
}

function boton(pantalla, texto) {
    return pantalla
        .findAll("button")
        .find((uno) => uno.text().includes(texto));
}

/**
 * Elige un archivo como lo haría quien lo busca en su disco. `files` es de solo
 * lectura, así que se declara sobre el elemento antes de disparar el evento.
 */
async function elegirArchivo(pantalla) {
    const campo = pantalla.find("#archivo-de-carga");

    Object.defineProperty(campo.element, "files", {
        value: [new File(["x"], "planta.xlsx")],
        configurable: true,
    });

    await campo.trigger("change");
}

describe("la pantalla de puesta en marcha", () => {
    it("no deja confirmar la carga mientras no haya un informe limpio", async () => {
        const pantalla = montar();

        await elegirArchivo(pantalla);

        expect(boton(pantalla, "Confirmar la carga").attributes("disabled"))
            .toBeDefined();
    });

    it("sigue sin dejar confirmar con un informe que trae errores", async () => {
        const pantalla = montar({ informe: informeConErrores });

        await elegirArchivo(pantalla);

        expect(boton(pantalla, "Confirmar la carga").attributes("disabled"))
            .toBeDefined();
        expect(pantalla.text()).toContain(
            "34 filas leídas · 31 correctas · 3 con error.",
        );
        expect(pantalla.text()).toContain("Nada se ha guardado todavía");
    });

    it("deja confirmar cuando el informe está limpio y el archivo sigue elegido", async () => {
        const pantalla = montar({ informe: informeLimpio });

        await elegirArchivo(pantalla);

        expect(boton(pantalla, "Confirmar la carga").attributes("disabled"))
            .toBeUndefined();
    });

    it("no deja confirmar con el informe de otra plantilla", async () => {
        // El informe es de «Empleados actuales» y la plantilla elegida ya es
        // otra: confirmar aquí cargaría un archivo que nadie validó.
        const pantalla = montar({
            informe: informeLimpio,
            viendo: { empresa: 7, plantilla: "vacaciones" },
        });

        await elegirArchivo(pantalla);

        expect(boton(pantalla, "Confirmar la carga").attributes("disabled"))
            .toBeDefined();
    });

    it("enseña cada error con su número de fila y su motivo", () => {
        const pantalla = montar({ informe: informeConErrores });

        expect(pantalla.text()).toContain("«Sanitaz» no está en el catálogo.");
        expect(pantalla.text()).toContain("está repetido en el archivo");
        // El error que no es de ninguna columna no se inventa una.
        expect(pantalla.text()).toContain("—");
    });

    it("vuelve a pedir la pantalla al servidor al cambiar de plantilla", async () => {
        const pantalla = montar({ informe: informeLimpio });

        await pantalla.findAll("select")[1].setValue("vacaciones");

        expect(pedir).toHaveBeenCalledWith(
            "/proveedor/puesta-en-marcha",
            expect.objectContaining({ plantilla: "vacaciones" }),
            expect.anything(),
        );
    });

    it("valida y confirma por direcciones distintas", async () => {
        const pantalla = montar({ informe: informeLimpio });

        await elegirArchivo(pantalla);

        await boton(pantalla, "Validar sin cargar").trigger("click");
        expect(enviar).toHaveBeenCalledWith(
            "/proveedor/puesta-en-marcha/simular",
            expect.objectContaining({ forceFormData: true }),
        );

        await boton(pantalla, "Confirmar la carga").trigger("click");
        expect(enviar).toHaveBeenCalledWith(
            "/proveedor/puesta-en-marcha/cargar",
            expect.objectContaining({ forceFormData: true }),
        );
    });

    it("avisa del orden de carga cuando el histórico llega antes que la planta", () => {
        const sinPlanta = montar({
            viendo: { empresa: 7, plantilla: "vacaciones" },
        });

        expect(sinPlanta.text()).toContain("Primero la plantilla de empleados");

        const conPlanta = montar({
            viendo: { empresa: 7, plantilla: "vacaciones" },
            estadoDeLaEmpresa: {
                ...base.estadoDeLaEmpresa,
                plantaCargada: true,
            },
        });

        expect(conPlanta.text()).not.toContain(
            "Primero la plantilla de empleados",
        );
    });

    it("dice qué plantillas ya entraron y cuáles faltan", () => {
        const pantalla = montar({
            estadoDeLaEmpresa: {
                ...base.estadoDeLaEmpresa,
                plantaCargada: true,
                plantillas: [
                    {
                        clave: "empleados",
                        nombre: "Empleados actuales",
                        obligatoria: true,
                        cargada: { fecha: "18/02/2026", filas: 32, quien: "David" },
                    },
                    ...base.estadoDeLaEmpresa.plantillas.slice(1),
                ],
            },
        });

        expect(pantalla.text()).toContain("Cargada");
        expect(pantalla.text()).toContain("Sin cargar");
        expect(pantalla.text()).toContain("18/02/2026");
        expect(pantalla.text()).toContain("obligatoria");
    });

    it("promete la transacción con las palabras del prototipo", () => {
        expect(montar().text()).toContain("o entra todo o no entra nada");
    });
});
