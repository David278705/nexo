import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/proveedor/empresas/7",
    props: {
        usuario: { nombre: "David Lozano", correo: "david@nexo.co", modulos: [] },
        secciones: [
            {
                clave: "empresas",
                etiqueta: "Empresas",
                grupo: "Clientes",
                url: "/proveedor/empresas",
            },
        ],
    },
};

const enviar = vi.fn();
const eliminar = vi.fn();

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    usePage: () => pagina,
    router: {
        post: (...argumentos) => enviar(...argumentos),
        delete: (...argumentos) => eliminar(...argumentos),
        get: vi.fn(),
        visit: vi.fn(),
    },
    Link: {
        name: "Link",
        props: ["href"],
        template: "<a :href='href'><slot /></a>",
    },
}));

const FichaDeEmpresa = (await import("./FichaDeEmpresa.vue")).default;

const props = {
    empresa: {
        id: 7,
        nombre: "Distribuidora El Progreso S.A.S.",
        nit: "900123456-7",
        estado: "Activa",
        estadoTono: "exito",
        clienteDesde: "Cliente desde el 12 de febrero de 2026",
    },
    cifras: [
        { etiqueta: "Plan", valor: "Pequeña" },
        { etiqueta: "Empleados", valor: "34 de 50" },
    ],
    pestanas: [
        { clave: "resumen", titulo: "Resumen" },
        { clave: "parametros", titulo: "Parámetros" },
        { clave: "usuarios", titulo: "Usuarios" },
        { clave: "uso", titulo: "Uso" },
        { clave: "suscripcion", titulo: "Suscripción" },
        { clave: "alertas", titulo: "Alertas" },
        { clave: "bitacora", titulo: "Bitácora" },
    ],
    resumen: {
        datos: [{ etiqueta: "Razón social", valor: "Distribuidora El Progreso S.A.S." }],
        plan: [{ etiqueta: "Plan", valor: "Pequeña" }],
        alta: [
            { etiqueta: "Fecha de alta", valor: "12 de febrero de 2026" },
            { etiqueta: "Creada por", valor: "David Lozano" },
        ],
        certificado: null,
        sinCertificado: "Pendiente de recibir",
    },
    parametros: [
        {
            clave: "perfil_sociodemografico_habilitado",
            titulo: "Perfil sociodemográfico",
            explicacion: "Habilita un bloque opcional.",
            valor: "no",
            opciones: [
                { valor: "si", texto: "Sí, habilitado" },
                { valor: "no", texto: "No" },
            ],
        },
    ],
    perfil: { habilitado: false, aviso: "Los 3 empleados ya registrados quedarán en blanco." },
    usuarios: [
        {
            id: 3,
            nombre: "Ana María Gil",
            correo: "ana@elprogreso.com",
            rol: "Superadministrador",
            ultimoAcceso: "Nunca",
            invitacionPendiente: true,
        },
    ],
    uso: {
        cifras: [{ etiqueta: "Empleados cargados", valor: "34" }],
        novedadesPorMes: [
            { mes: "Agosto de 2026", total: 7 },
            { mes: "Julio de 2026", total: 3 },
        ],
        modulos: [
            { modulo: "Incapacidades", registros: 5, usa: true, ultimo: "20/08/2026" },
            { modulo: "Vacaciones", registros: 0, usa: false, ultimo: "Sin registros" },
        ],
    },
    suscripcion: {
        existe: true,
        id: 12,
        estado: "Activa",
        estadoTono: "exito",
        suspendida: false,
        cobro: "Mora de 22 días",
        cobroTono: "error",
        diasDeAtraso: 22,
        datos: [
            { etiqueta: "Plan", valor: "Pequeña · 50 empleados · 2 usuarios" },
            { etiqueta: "Valor mensual", valor: "$ 380.000" },
        ],
        suspension: null,
        reactivacion: null,
        historial: [
            {
                id: 5,
                fecha: "05/08/2026",
                monto: "$ 380.000",
                numeroFactura: "FE-1001",
                estado: "Pagada",
                tono: "exito",
                anulada: false,
            },
        ],
        planes: [{ valor: "2", texto: "Mediana · 200 empleados · 4 usuarios" }],
        aviso: "Suspender bloquea el acceso pero no elimina nada.",
        consecuenciaDeAnular: "El próximo cobro volverá al 5 de julio de 2026.",
    },
    bitacora: {
        titulo: "Bitácora del cliente",
        ayuda: "Es interna del equipo: el cliente no la ve.",
        ayudaDeLaNota: "Lo que se habló, en una o dos frases.",
        vacia: {
            titulo: "Todavía no hay notas de este cliente",
            descripcion: "Aquí queda lo que se habló con él.",
        },
        notas: [
            {
                id: 4,
                fecha: "12/08/2026",
                autor: "Angela",
                texto: "Llamó Ana María por el cálculo de una licencia. Quedó clara.",
            },
        ],
        visitas: {
            titulo: "Accesos del proveedor al entorno",
            ayuda: "Cada uno está también en la auditoría de esta empresa.",
            vacio: "Nadie del equipo ha entrado al entorno de este cliente.",
            filas: [
                {
                    id: 2,
                    cuando: "23/08/2026, 9:15 a. m.",
                    quien: "David Lozano",
                    motivo: "Revisar el cálculo que reportó Ana María.",
                    modo: "Consulta",
                    tono: "neutro",
                    duracion: "18 minutos",
                },
            ],
        },
    },
    acceso: {
        titulo: "Entrar al entorno del cliente",
        boton: "Entrar como el cliente",
        tituloCondiciones: "Tres condiciones que no son negociables",
        condiciones: [
            "Queda registrado en la auditoría de esa empresa, visible para el cliente.",
            "Es de solo lectura. Modificar datos exige activarlo de forma expresa.",
            "El acuerdo de tratamiento de datos lo contempla.",
        ],
        ayudaDelMotivo: "Queda en el registro. Escriba algo que tenga sentido dentro de seis meses.",
        etiquetaDeEscritura: "Necesito modificar datos, no solo consultar",
    },
    alertas: {
        filas: [
            {
                id: 9,
                programada: "17/08/2026",
                enviada: "17/08/2026, 6:02 a. m.",
                tipo: "Víspera del periodo de prueba",
                empleado: "Ana Torres",
                destinatarios: ["gestion@elprogreso.co"],
                estado: "Enviada",
                tono: "exito",
                intentos: 1,
            },
        ],
        total: 1,
        maximo: 50,
        hayMas: false,
        consolaUrl: "/proveedor/alertas?empresa=7",
        vacio: {
            titulo: "Todavía no se le ha enviado ninguna alerta",
            descripcion: "Cuando el proceso diario tenga algo que avisarle a esta empresa, aparecerá aquí con su resultado.",
        },
    },
    volver: "/proveedor/empresas",
};

function montar(cambios = {}) {
    return mount(FichaDeEmpresa, {
        props: { ...props, ...cambios },
        attachTo: document.body,
    });
}

async function irA(pantalla, titulo) {
    const pestana = pantalla
        .findAll("[role='tab']")
        .find((boton) => boton.text() === titulo);

    await pestana.trigger("click");

    return pantalla;
}

/**
 * Los modales viven en un `Teleport` al `body`, así que sus botones no están
 * dentro del componente montado: se buscan en el documento, igual que en
 * `Modal.test.js`.
 */
function botonDelModal(texto) {
    return [...document.body.querySelectorAll('[role="dialog"] button')].find(
        (uno) => uno.textContent.includes(texto),
    );
}

function pulsar(elemento) {
    elemento.dispatchEvent(new MouseEvent("click", { bubbles: true }));
}

beforeEach(() => {
    enviar.mockClear();
    eliminar.mockClear();
    document.body.innerHTML = "";
});

describe("la ficha de la empresa", () => {
    it("abre en el resumen, con las siete pestañas del documento", () => {
        const pantalla = montar();

        expect(pantalla.findAll("[role='tab']")).toHaveLength(7);
        expect(pantalla.text()).toContain("Cliente desde el 12 de febrero de 2026");
        expect(pantalla.text()).toContain("Creada por");
        expect(pantalla.text()).toContain("Pendiente de recibir");
    });

    it("avisa al activar el perfil sociodemográfico, no antes", async () => {
        const pantalla = await irA(montar(), "Parámetros");

        expect(pantalla.text()).not.toContain("ya registrados quedarán");

        await pantalla.find("select").setValue("si");

        expect(pantalla.text()).toContain("ya registrados quedarán");
    });

    it("ofrece reenviar la invitación solo a quien no la ha aceptado", async () => {
        const pantalla = await irA(montar(), "Usuarios");

        const reenviar = pantalla
            .findAll("button")
            .find((boton) => boton.text().includes("Reenviar invitación"));

        await reenviar.trigger("click");

        expect(enviar).toHaveBeenCalledWith(
            "/proveedor/empresas/7/usuarios/3/invitacion",
            {},
            expect.anything(),
        );
    });

    it("promete que la contraseña no se puede ver desde ninguna parte", async () => {
        const pantalla = await irA(montar(), "Usuarios");

        expect(pantalla.text()).toContain(
            "no se puede ver desde aquí ni desde ninguna otra parte",
        );
    });

    it("enseña en «Alertas» el registro real de lo que se le ha enviado a la empresa", async () => {
        const pantalla = await irA(montar(), "Alertas");

        expect(pantalla.text()).toContain("Víspera del periodo de prueba");
        expect(pantalla.text()).toContain("gestion@elprogreso.co");
        expect(pantalla.text()).toContain("Enviada");
    });

    it("en «Alertas» sin envíos muestra el estado vacío, no un aviso de fase pendiente", async () => {
        const pantalla = await irA(
            montar({
                alertas: { ...props.alertas, filas: [], total: 0 },
            }),
            "Alertas",
        );

        expect(pantalla.text()).toContain(
            "Todavía no se le ha enviado ninguna alerta",
        );
    });

    it("en «Uso» cuenta las novedades por mes y marca los módulos que usa de verdad", async () => {
        const pantalla = await irA(montar(), "Uso");

        expect(pantalla.text()).toContain("Agosto de 2026");
        expect(pantalla.text()).toContain("Novedades registradas por mes");
        expect(pantalla.text()).toContain("Incapacidades");
        expect(pantalla.text()).toContain("Sin registros");
    });

    it("enseña la bitácora con sus notas y dice que es interna", async () => {
        const pantalla = await irA(montar(), "Bitácora");

        expect(pantalla.text()).toContain("el cliente no la ve");
        expect(pantalla.text()).toContain("12/08/2026 · Angela");
        expect(pantalla.text()).toContain("Quedó clara");
    });

    it("enseña, al lado de las notas, los accesos que el cliente sí ve", async () => {
        const pantalla = await irA(montar(), "Bitácora");

        expect(pantalla.text()).toContain("Accesos del proveedor al entorno");
        expect(pantalla.text()).toContain("Revisar el cálculo que reportó Ana María.");
        expect(pantalla.text()).toContain("18 minutos");
    });

    it("pide confirmar antes de quitar una nota, con su texto", async () => {
        const pantalla = await irA(montar(), "Bitácora");

        const quitar = pantalla
            .findAll("button")
            .find((boton) => boton.text() === "Quitar");

        await quitar.trigger("click");

        const dialogo = document.body.querySelector('[role="dialog"]');

        expect(dialogo.textContent).toContain("Quedó clara");
        expect(eliminar).not.toHaveBeenCalled();
    });

    it("quita una nota por su propia dirección al confirmar", async () => {
        const pantalla = await irA(montar(), "Bitácora");

        const quitar = pantalla
            .findAll("button")
            .find((boton) => boton.text() === "Quitar");

        await quitar.trigger("click");
        pulsar(botonDelModal("Quitar la nota"));
        await pantalla.vm.$nextTick();

        expect(eliminar).toHaveBeenCalledWith(
            "/proveedor/empresas/7/notas/4",
            expect.anything(),
        );
    });

    it("enseña las tres condiciones antes de entrar al entorno del cliente", async () => {
        const pantalla = montar();

        const entrar = pantalla
            .findAll("button")
            .find((boton) => boton.text() === "Entrar como el cliente");

        await entrar.trigger("click");

        // El modal viaja a `body` con `Teleport`, así que se lee de ahí y no
        // del componente, como en `Modal.test.js`.
        const dialogo = document.body.querySelector('[role="dialog"]');

        expect(dialogo.textContent).toContain("Tres condiciones que no son negociables");
        expect(dialogo.textContent).toContain("visible para el cliente");
        expect(dialogo.textContent).toContain("Motivo del acceso");
        expect(dialogo.textContent).toContain("Necesito modificar datos, no solo consultar");

        pantalla.unmount();
    });

    it("enseña la suscripción con su estado de cobro y su historial", async () => {
        const pantalla = await irA(montar(), "Suscripción");

        expect(pantalla.text()).toContain("Mora de 22 días");
        expect(pantalla.text()).toContain("$ 380.000");
        expect(pantalla.text()).toContain("FE-1001");
        expect(pantalla.text()).toContain("Suspender servicio");
    });

    it("ofrece reactivar, y no suspender, lo que ya está suspendido", async () => {
        const pantalla = await irA(
            montar({
                suscripcion: {
                    ...props.suscripcion,
                    suspendida: true,
                    estado: "Suspendida",
                    estadoTono: "error",
                    suspension: {
                        motivo: "Mora de 34 días, sin acuerdo de pago.",
                        cuando: "23/08/2026, 9:15 a. m.",
                    },
                },
            }),
            "Suscripción",
        );

        expect(pantalla.text()).toContain("Reactivar servicio");
        expect(pantalla.text()).not.toContain("Suspender servicio");
        // La constancia se lee donde se decide reactivar.
        expect(pantalla.text()).toContain("Mora de 34 días, sin acuerdo de pago.");
    });

    it("pide confirmar antes de anular un pago, con la fecha nueva del cobro", async () => {
        const pantalla = await irA(montar(), "Suscripción");

        const anular = pantalla
            .findAll("button")
            .find((boton) => boton.text() === "Anular");

        await anular.trigger("click");

        const dialogo = document.body.querySelector('[role="dialog"]');

        expect(dialogo.textContent).toContain("FE-1001");
        expect(dialogo.textContent).toContain(
            "El próximo cobro volverá al 5 de julio de 2026.",
        );
        expect(enviar).not.toHaveBeenCalled();
    });

    it("anula el pago al confirmar", async () => {
        const pantalla = await irA(montar(), "Suscripción");

        const anular = pantalla
            .findAll("button")
            .find((boton) => boton.text() === "Anular");

        await anular.trigger("click");
        pulsar(botonDelModal("Anular el pago"));
        await pantalla.vm.$nextTick();

        expect(enviar).toHaveBeenCalledWith(
            "/proveedor/suscripciones/12/pagos/5/anular",
            {},
            expect.anything(),
        );
    });

    it("dice que a una empresa sin suscripción no se le cobra todavía", async () => {
        const pantalla = await irA(
            montar({
                suscripcion: {
                    existe: false,
                    titulo: "A esta empresa no se le cobra todavía",
                    descripcion: "No tiene suscripción abierta.",
                    url: "/proveedor/suscripciones",
                },
            }),
            "Suscripción",
        );

        expect(pantalla.text()).toContain("no se le cobra todavía");
        expect(pantalla.text()).not.toContain("Registrar pago");
    });
});
