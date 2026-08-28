import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const put = vi.fn();

const pagina = {
    url: "/configuracion",
    props: {
        usuario: {
            nombre: "Angela Lozano",
            correo: "angela@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [
                {
                    clave: "configuracion",
                    etiqueta: "Configuración",
                    grupo: "Sistema",
                    url: "/configuracion",
                },
            ],
        },
    },
};

/*
 * El formulario de Inertia se reemplaza por un objeto reactivo con la misma
 * forma, incluido `isDirty`: lo que se comprueba aquí es cuándo la pantalla deja
 * guardar y a dónde manda los valores, no la biblioteca.
 */
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
    useForm: (valores) => {
        const inicial = { ...valores };
        const formulario = reactive({
            ...valores,
            errors: {},
            processing: false,
            put,
            get isDirty() {
                return Object.keys(inicial).some(
                    (clave) => formulario[clave] !== inicial[clave],
                );
            },
        });

        return formulario;
    },
}));

const Parametros = (await import("./Parametros.vue")).default;

const props = {
    empresa: {
        nombre: "Distribuidora El Progreso S.A.S.",
        nit: "900123456-7",
        nota: "Los datos de la empresa los cambia el proveedor.",
    },
    parametros: [
        {
            clave: "sabado_laboral",
            titulo: "¿El sábado es día laboral?",
            explicacion: "Afecta el cálculo de los días de vacaciones.",
            valor: "no",
            opciones: [
                { valor: "si", texto: "Sí, el sábado es día laboral" },
                { valor: "no", texto: "No, la empresa no trabaja los sábados" },
            ],
        },
        {
            clave: "suspensiones_afectan_vacaciones",
            titulo: "¿Las suspensiones afectan la acumulación de vacaciones?",
            explicacion: "Reducen proporcionalmente los 1,25 días del mes.",
            valor: "si",
            opciones: [
                { valor: "si", texto: "Sí, reducen la acumulación" },
                { valor: "no", texto: "No, no la afectan" },
            ],
        },
        {
            clave: "perfil_sociodemografico_habilitado",
            titulo: "Perfil sociodemográfico",
            explicacion: "Habilita un bloque opcional con estado civil.",
            valor: "no",
            opciones: [
                { valor: "si", texto: "Sí, habilitado" },
                { valor: "no", texto: "No" },
            ],
        },
    ],
    destinatarios: {
        titulo: "Correos que reciben las alertas",
        explicacion:
            "Cada alerta llega a los administradores con acceso al módulo correspondiente.",
        filas: [
            { etiqueta: "angela@progreso.co", valor: "Todos los módulos" },
            { etiqueta: "marcela@progreso.co", valor: "Vacaciones" },
        ],
    },
    perfil: {
        habilitado: false,
        aviso: "Los 34 empleados ya registrados quedarán con estos campos vacíos.",
    },
};

function montar(cambios = {}) {
    return mount(Parametros, { props: { ...props, ...cambios } });
}

describe("la pantalla de parámetros", () => {
    beforeEach(() => {
        put.mockClear();
    });

    it("pinta los cuatro parámetros con su explicación", () => {
        const texto = montar().text();

        expect(texto).toContain("¿El sábado es día laboral?");
        expect(texto).toContain("Reducen proporcionalmente los 1,25 días");
        expect(texto).toContain("Perfil sociodemográfico");
        expect(texto).toContain("Correos que reciben las alertas");
        expect(texto).toContain("marcela@progreso.co");
        expect(texto).toContain("Vacaciones");
    });

    it("empieza con el valor guardado de cada parámetro", () => {
        const selectores = montar().findAll("select");

        expect(selectores).toHaveLength(3);
        expect(selectores[0].element.value).toBe("no");
        expect(selectores[1].element.value).toBe("si");
    });

    it("no deja guardar mientras no se cambie nada", () => {
        const pantalla = montar();

        expect(pantalla.find("button[type='submit']").element.disabled).toBe(
            true,
        );
        expect(pantalla.text()).toContain("No hay cambios por guardar");
    });

    it("habilita el botón al cambiar un parámetro", async () => {
        const pantalla = montar();

        await pantalla.findAll("select")[0].setValue("si");

        expect(pantalla.find("button[type='submit']").element.disabled).toBe(
            false,
        );
    });

    it("manda los valores a la dirección de los parámetros", async () => {
        const pantalla = montar();

        await pantalla.findAll("select")[0].setValue("si");
        await pantalla.find("form").trigger("submit");

        expect(put).toHaveBeenCalledWith(
            "/configuracion/parametros",
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it("advierte al activar el perfil sociodemográfico", async () => {
        const pantalla = montar();

        expect(pantalla.text()).not.toContain("quedarán con estos campos");

        await pantalla.findAll("select")[2].setValue("si");

        expect(pantalla.text()).toContain(
            "Los 34 empleados ya registrados quedarán con estos campos vacíos.",
        );
    });

    it("no advierte nada si el perfil ya estaba habilitado", async () => {
        const pantalla = montar({
            perfil: { habilitado: true, aviso: null },
            parametros: props.parametros.map((parametro) =>
                parametro.clave === "perfil_sociodemografico_habilitado"
                    ? { ...parametro, valor: "si" }
                    : parametro,
            ),
        });

        await pantalla.findAll("select")[2].setValue("no");
        await pantalla.findAll("select")[2].setValue("si");

        expect(pantalla.text()).not.toContain("quedarán con estos campos");
    });

    it("enseña los datos de la empresa en solo lectura, con su nota", async () => {
        const pantalla = montar();

        await pantalla.findAll("button[role='tab']")[1].trigger("click");

        expect(pantalla.text()).toContain("900123456-7");
        expect(pantalla.text()).toContain(
            "Los datos de la empresa los cambia el proveedor.",
        );
        expect(pantalla.findAll("select")).toHaveLength(0);
    });

    it("dice que la pestaña de facturación llega en su propia tarea", async () => {
        const pantalla = montar();

        await pantalla.findAll("button[role='tab']")[3].trigger("click");

        expect(pantalla.text()).toContain(
            "Esta pestaña todavía no tiene contenido",
        );
    });

    it("pinta el listado de usuarios con su rol y sus módulos", async () => {
        const pantalla = montar({
            usuarios: [
                {
                    id: 1,
                    nombre: "Angela Lozano",
                    correo: "angela@progreso.co",
                    rol: "Superadministrador",
                    esSuperadministrador: true,
                    modulos: "Todos los módulos",
                    modulosAsignados: [],
                    invitacionPendiente: false,
                    consecuenciaDeDesactivar: "",
                },
                {
                    id: 2,
                    nombre: "Marcela Ríos",
                    correo: "marcela@progreso.co",
                    rol: "Administrador",
                    esSuperadministrador: false,
                    modulos: "Vacaciones",
                    modulosAsignados: ["vacaciones"],
                    invitacionPendiente: true,
                    consecuenciaDeDesactivar: "Vas a desactivar a Marcela Ríos.",
                },
            ],
            modulosAsignables: [{ valor: "vacaciones", texto: "Vacaciones" }],
        });

        await pantalla.findAll("button[role='tab']")[2].trigger("click");

        expect(pantalla.text()).toContain("Angela Lozano");
        expect(pantalla.text()).toContain("Superadministrador");
        expect(pantalla.text()).toContain("Todos los módulos");
        expect(pantalla.text()).toContain("Marcela Ríos");
        expect(pantalla.text()).toContain("Editar módulos");
        expect(pantalla.text()).toContain("Desactivar");
        expect(pantalla.text()).not.toContain(
            "Esta pestaña todavía no tiene contenido",
        );
    });

    it("no ofrece editar ni desactivar al superadministrador", async () => {
        const pantalla = montar({
            usuarios: [
                {
                    id: 1,
                    nombre: "Angela Lozano",
                    correo: "angela@progreso.co",
                    rol: "Superadministrador",
                    esSuperadministrador: true,
                    modulos: "Todos los módulos",
                    modulosAsignados: [],
                    invitacionPendiente: false,
                    consecuenciaDeDesactivar: "",
                },
            ],
        });

        await pantalla.findAll("button[role='tab']")[2].trigger("click");

        expect(pantalla.text()).toContain("Acceso total, no editable");
        expect(pantalla.text()).not.toContain("Editar módulos");
    });
});
