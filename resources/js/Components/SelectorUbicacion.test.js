import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import SelectorUbicacion from "./SelectorUbicacion.vue";

const paises = [
    { valor: "CO", texto: "Colombia" },
    { valor: "VE", texto: "Venezuela" },
];

const departamentos = [
    {
        valor: 1,
        texto: "Antioquia",
        ciudades: [
            { valor: 10, texto: "Medellín" },
            { valor: 11, texto: "Envigado" },
        ],
    },
    {
        valor: 2,
        texto: "Valle del Cauca",
        ciudades: [{ valor: 20, texto: "Cali" }],
    },
    {
        valor: 3,
        texto: "Cundinamarca",
        ciudades: [{ valor: 30, texto: "Soacha" }],
    },
    {
        valor: 4,
        texto: "Bogotá D.C.",
        ciudades: [{ valor: 40, texto: "Bogotá D.C." }],
    },
];

function montar(ubicacion, extras = {}) {
    return mount(SelectorUbicacion, {
        props: {
            modelValue: { pais: "", departamento: "", ciudad: "", ...ubicacion },
            paises,
            departamentos,
            codigoDeColombia: "CO",
            ...extras,
        },
    });
}

// El texto de cada `option` de un `select`, en el orden en que aparece.
function opciones(componente, indice) {
    return componente
        .findAll("select")
        [indice].findAll("option")
        .map((opcion) => opcion.text());
}

describe("SelectorUbicacion", () => {
    it("pide el país con una lista y nada más mientras no se elija", () => {
        const componente = montar({});

        // País y, debajo, departamento y ciudad de Colombia: mientras el país
        // esté vacío no es Colombia, así que van los dos campos de texto.
        expect(componente.findAll("select")).toHaveLength(1);
        expect(componente.findAll("input[type='text']")).toHaveLength(2);
    });

    it("encadena las ciudades del departamento elegido", () => {
        const componente = montar({ pais: "CO", departamento: 1 });

        // Tres selectores: país, departamento y ciudad.
        expect(componente.findAll("select")).toHaveLength(3);
        expect(opciones(componente, 2)).toEqual([
            "Seleccione…",
            "Medellín",
            "Envigado",
        ]);
    });

    it("no ofrece ciudades de otro departamento", () => {
        const componente = montar({ pais: "CO", departamento: 2 });

        expect(opciones(componente, 2)).toEqual(["Seleccione…", "Cali"]);
    });

    it("no ofrece ciudad alguna antes de elegir departamento", () => {
        const componente = montar({ pais: "CO" });

        expect(opciones(componente, 2)).toEqual(["Elija primero el departamento"]);
    });

    it("digita el departamento y la ciudad cuando el país no es Colombia", () => {
        const componente = montar({ pais: "VE" });

        expect(componente.findAll("select")).toHaveLength(1);
        expect(componente.findAll("input[type='text']")).toHaveLength(2);
    });

    it("limpia departamento y ciudad al cambiar de país", async () => {
        const componente = montar({ pais: "CO", departamento: 1, ciudad: 10 });

        await componente.findAll("select")[0].setValue("VE");

        expect(componente.emitted("update:modelValue")[0][0]).toEqual({
            pais: "VE",
            departamento: "",
            ciudad: "",
        });
    });

    it("limpia la ciudad al cambiar de departamento", async () => {
        const componente = montar({ pais: "CO", departamento: 1, ciudad: 10 });

        await componente.findAll("select")[1].setValue("2");

        expect(componente.emitted("update:modelValue")[0][0]).toEqual({
            pais: "CO",
            departamento: "2",
            ciudad: "",
        });
    });

    it("no pregunta el país cuando la ubicación es la residencia", () => {
        // La residencia siempre es en Colombia: el sistema solo gestiona
        // contratos laborales colombianos.
        const componente = montar({}, { soloColombia: true });

        expect(componente.findAll("select")).toHaveLength(2);
        expect(componente.text()).not.toContain("País");
    });

    it("completa las etiquetas con el sufijo que recibe", () => {
        const componente = montar({}, { sufijo: "de nacimiento" });

        expect(componente.text()).toContain("País de nacimiento");
        expect(componente.text()).toContain("Departamento de nacimiento");
        expect(componente.text()).toContain("Ciudad de nacimiento");
    });

    /*
     * D.7 / HD-07: el catálogo no se toca —Bogotá sigue sin estar entre los
     * municipios de Cundinamarca, porque el DANE la trata como su propio
     * departamento—, así que quien busca Cundinamarca porque nació o vive en
     * Bogotá necesita que la pantalla se lo explique.
     */
    it("avisa que Bogotá se busca aparte al elegir Cundinamarca", () => {
        const componente = montar({ pais: "CO", departamento: 3 });
        const aviso = componente.find('[role="status"]');

        expect(aviso.exists()).toBe(true);
        expect(aviso.text()).toContain("Bogotá D.C.");
    });

    it("no avisa de Bogotá con otro departamento elegido", () => {
        const componente = montar({ pais: "CO", departamento: 1 });

        expect(componente.find('[role="status"]').exists()).toBe(false);
    });

    it("no avisa de Bogotá mientras no se haya elegido departamento", () => {
        const componente = montar({ pais: "CO" });

        expect(componente.find('[role="status"]').exists()).toBe(false);
    });

    it("escribir «Bogotá» en el buscador de departamentos encuentra «Bogotá D.C.»", async () => {
        // Con más de diez departamentos el Selector cambia a combobox con
        // búsqueda (documento 3, 3.4): es la lista real, no la reducida de
        // arriba.
        const muchosDepartamentos = [
            ...departamentos,
            ...Array.from({ length: 8 }, (_, indice) => ({
                valor: 100 + indice,
                texto: `Departamento ${indice}`,
                ciudades: [],
            })),
        ];

        const componente = mount(SelectorUbicacion, {
            props: {
                modelValue: { pais: "CO", departamento: "", ciudad: "" },
                paises,
                departamentos: muchosDepartamentos,
                codigoDeColombia: "CO",
            },
            attachTo: document.body,
        });

        const campoDeDepartamento = componente.find('[role="combobox"]');

        await campoDeDepartamento.trigger("focus");
        await campoDeDepartamento.setValue("Bogotá");
        await campoDeDepartamento.trigger("input");

        expect(
            componente.findAll('[role="option"]').map((opcion) => opcion.text()),
        ).toEqual(["Bogotá D.C."]);

        componente.unmount();
    });
});
