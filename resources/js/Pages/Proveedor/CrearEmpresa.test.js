import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const post = vi.fn();

const pagina = {
    url: "/proveedor/empresas/nueva",
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
        const formulario = reactive({
            ...valores,
            errors: {},
            processing: false,
            post,
            setError(campo, mensaje) {
                formulario.errors[campo] = mensaje;
            },
        });

        return formulario;
    },
}));

const CrearEmpresa = (await import("./CrearEmpresa.vue")).default;

const props = {
    pasos: ["Datos de la empresa", "Parámetros", "Plan y cuenta"],
    parametros: [
        {
            clave: "sabado_laboral",
            titulo: "¿El sábado es día laboral?",
            explicacion: "Afecta el cálculo de los días de vacaciones.",
            opciones: [
                { valor: "si", texto: "Sí, el sábado es día laboral" },
                { valor: "no", texto: "No, la empresa no trabaja los sábados" },
            ],
        },
        {
            clave: "suspensiones_afectan_vacaciones",
            titulo: "¿Las suspensiones afectan la acumulación de vacaciones?",
            explicacion: "Reducen proporcionalmente los 1,25 días del mes.",
            opciones: [
                { valor: "si", texto: "Sí, reducen la acumulación" },
                { valor: "no", texto: "No, no la afectan" },
            ],
        },
        {
            clave: "perfil_sociodemografico_habilitado",
            titulo: "Perfil sociodemográfico",
            explicacion: "Habilita un bloque opcional con estado civil.",
            opciones: [
                { valor: "si", texto: "Sí, habilitado" },
                { valor: "no", texto: "No" },
            ],
        },
    ],
    ubicaciones: {
        codigoDeColombia: "CO",
        paises: [{ valor: "CO", texto: "Colombia" }],
        departamentos: [
            {
                valor: 5,
                texto: "Antioquia",
                ciudades: [{ valor: 51, texto: "Medellín" }],
            },
        ],
    },
    certificado: { formatos: ["pdf", "jpg"], tamanoMaximoEnKb: 10240 },
    volver: "/proveedor/empresas",
};

function montar() {
    return mount(CrearEmpresa, { props });
}

/*
 * Los pasos se ocultan con `v-show` y no con `v-if`, así que todos los campos
 * están siempre en el árbol: lo escrito en un paso no se pierde al retroceder.
 * Por eso los índices de aquí son los del formulario entero.
 *
 * Selectores: 0 departamento · 1 ciudad · 2, 3 y 4 los tres parámetros.
 * Textos: 0 razón social · 1 NIT · 2 teléfono · 3 plan · 4 superadministrador.
 */
async function completarElPrimerPaso(pantalla) {
    const textos = pantalla.findAll("input[type='text']");

    await textos[0].setValue("Distribuidora El Progreso S.A.S.");
    await textos[1].setValue("900123456-7");
    await textos[2].setValue("604 444 5566");

    await pantalla.findAll("select")[0].setValue("5");
    await pantalla.findAll("select")[1].setValue("51");

    await pantalla.find("form").trigger("submit");
}

async function completarLosParametros(pantalla) {
    const selectores = pantalla.findAll("select");

    await selectores[2].setValue("si");
    await selectores[3].setValue("no");
    await selectores[4].setValue("si");

    await pantalla.find("form").trigger("submit");
}

async function completarElPlanYLaCuenta(pantalla) {
    const textos = pantalla.findAll("input[type='text']");
    const numeros = pantalla.findAll("input[type='number']");

    await textos[3].setValue("Pequeña — hasta 50 empleados");
    await numeros[0].setValue("50");
    await numeros[1].setValue("2");
    await pantalla.find("input[type='date']").setValue("2026-09-01");
    await textos[4].setValue("Ana María Gil");
    await pantalla.find("input[type='email']").setValue("ana@elprogreso.com");

    await pantalla.find("form").trigger("submit");
}

describe("el asistente de crear empresa", () => {
    beforeEach(() => {
        post.mockClear();
    });

    it("empieza en el primer paso con los títulos que manda el servidor", () => {
        const texto = montar().text();

        expect(texto).toContain("Datos de la empresa");
        expect(texto).toContain("Parámetros");
        expect(texto).toContain("Plan y cuenta");
        expect(texto).toContain("Paso 1 de 3");
    });

    it("no deja avanzar con los datos de la empresa vacíos", async () => {
        const pantalla = montar();

        await pantalla.find("form").trigger("submit");
        await pantalla.vm.$nextTick();

        expect(pantalla.text()).toContain("Este dato es obligatorio.");
        expect(pantalla.text()).toContain("Paso 1 de 3");
    });

    it("avanza a los parámetros con la explicación de cada uno", async () => {
        const pantalla = montar();

        await completarElPrimerPaso(pantalla);

        expect(pantalla.text()).toContain("Paso 2 de 3");
        expect(pantalla.text()).toContain(
            "Reducen proporcionalmente los 1,25 días del mes.",
        );
        expect(pantalla.text()).toContain("Se preguntan y se deciden en vivo");
    });

    it("guarda contra la ruta del panel al terminar los tres pasos", async () => {
        const pantalla = montar();

        await completarElPrimerPaso(pantalla);
        await completarLosParametros(pantalla);

        expect(pantalla.text()).toContain("Paso 3 de 3");

        await completarElPlanYLaCuenta(pantalla);

        expect(post).toHaveBeenCalledWith(
            "/proveedor/empresas",
            expect.any(Object),
        );
    });

    it("vuelve al paso donde el servidor encontró el error", async () => {
        const pantalla = montar();

        await completarElPrimerPaso(pantalla);
        await completarLosParametros(pantalla);
        await completarElPlanYLaCuenta(pantalla);

        // El NIT repetido solo lo puede descubrir el servidor, y el campo está
        // dos pasos atrás: sin esto el error se anunciaría donde no se ve.
        post.mock.calls[0][1].onError({ nit: "Ese NIT ya existe." });
        await pantalla.vm.$nextTick();

        expect(pantalla.text()).toContain("Paso 1 de 3");
    });

    it("dice que el certificado es opcional y qué admite", () => {
        const texto = montar().text();

        expect(texto).toContain("Certificado de Cámara de Comercio");
        expect(texto).toContain("(opcional)");
        expect(texto).toContain("PDF, JPG");
        expect(texto).toContain("hasta 10 MB");
    });
});
