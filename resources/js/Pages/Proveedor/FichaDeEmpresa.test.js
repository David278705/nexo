import { describe, expect, it, vi } from "vitest";
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

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    usePage: () => pagina,
    router: { post: (...argumentos) => enviar(...argumentos), get: vi.fn(), visit: vi.fn() },
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
        incompleto: "Las novedades por mes llegan en la fase 2.",
    },
    pendientes: {
        suscripcion: { titulo: "La suscripción todavía no existe", descripcion: "Es F5-04." },
        alertas: { titulo: "Todavía no se ha enviado ninguna alerta", descripcion: "Es la fase 3." },
        bitacora: { titulo: "La bitácora todavía no existe", descripcion: "Es F5-07." },
    },
    volver: "/proveedor/empresas",
};

function montar() {
    return mount(FichaDeEmpresa, { props });
}

async function irA(pantalla, titulo) {
    const pestana = pantalla
        .findAll("[role='tab']")
        .find((boton) => boton.text() === titulo);

    await pestana.trigger("click");

    return pantalla;
}

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

    it("dice de qué tarea depende cada pestaña que todavía no tiene contenido", async () => {
        const pantalla = montar();

        await irA(pantalla, "Suscripción");
        expect(pantalla.text()).toContain("La suscripción todavía no existe");

        await irA(pantalla, "Bitácora");
        expect(pantalla.text()).toContain("La bitácora todavía no existe");
    });
});
