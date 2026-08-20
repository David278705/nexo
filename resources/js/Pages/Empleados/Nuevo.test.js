import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/empleados/nuevo",
    props: {
        usuario: {
            nombre: "Angela Lozano",
            correo: "angela@progreso.co",
            empresa: "Distribuidora El Progreso S.A.S.",
            nit: "900123456-7",
            modulos: [],
        },
    },
};

// La recarga parcial que pide la advertencia de reingreso. Se espía en lugar de
// ejecutarse: lo que importa es con qué se llama, no que jsdom navegue.
const recargar = vi.fn();

vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    Head: { name: "Head", template: "<span />" },
    usePage: () => pagina,
    router: { reload: (...argumentos) => recargar(...argumentos) },
    Link: {
        name: "Link",
        props: ["href"],
        template: "<a :href='href'><slot /></a>",
    },
}));

const Nuevo = (await import("./Nuevo.vue")).default;

const opciones = {
    documentos: [{ valor: "cedula_ciudadania", texto: "Cédula de ciudadanía" }],
    generos: [
        { valor: "femenino", texto: "Femenino" },
        { valor: "masculino", texto: "Masculino" },
    ],
    contratos: [
        { valor: "indefinido", texto: "Término indefinido" },
        { valor: "fijo", texto: "Término fijo" },
        { valor: "obra_labor", texto: "Obra o labor" },
    ],
    riesgos: [{ valor: "riesgo_i", texto: "Riesgo I – Mínimo" }],
    contratoConFechaFin: "fijo",
    eps: [{ valor: 1, texto: "Sura EPS" }],
    fondosPension: [{ valor: 2, texto: "Porvenir" }],
    arls: [{ valor: 3, texto: "ARL Sura" }],
    cajasCompensacion: [{ valor: 4, texto: "Comfama" }],
    bancos: [{ valor: 5, texto: "Bancolombia" }],

    // Las seis listas del perfil sociodemográfico (documento 1, 5.2). Viajan
    // siempre, tenga la empresa el bloque encendido o apagado.
    estadosCiviles: [
        { valor: "soltero", texto: "Soltero(a)" },
        { valor: "union_libre", texto: "Unión libre" },
    ],
    nivelesEducativos: [
        { valor: "bachiller", texto: "Bachiller" },
        { valor: "tecnico_tecnologo", texto: "Técnico / Tecnólogo" },
    ],
    tiposVivienda: [
        { valor: "propia", texto: "Propia" },
        { valor: "arrendada", texto: "Arrendada" },
    ],
    estratos: [
        { valor: 1, texto: "Estrato 1" },
        { valor: 3, texto: "Estrato 3" },
    ],
    mediosTransporte: [
        { valor: "a_pie", texto: "A pie" },
        { valor: "transporte_publico", texto: "Transporte público" },
    ],
    tiemposDesplazamiento: [
        { valor: "entre_30_y_60_minutos", texto: "Entre 30 y 60 minutos" },
    ],
};

const ubicaciones = {
    paises: [
        { valor: "CO", texto: "Colombia" },
        { valor: "VE", texto: "Venezuela" },
    ],
    departamentos: [
        {
            valor: 1,
            texto: "Antioquia",
            ciudades: [
                { valor: 10, texto: "Medellín" },
                { valor: 11, texto: "Envigado" },
            ],
        },
    ],
    codigoDeColombia: "CO",
};

function montar(props = {}) {
    return mount(Nuevo, { props: { opciones, ubicaciones, ...props } });
}

// El número de documento es el primer campo de texto del asistente.
function campoDelDocumento(componente) {
    return componente.findAll('input[type="text"]')[0];
}

// Todo lo obligatorio del primer paso, para poder comprobar que se avanza.
function llenarIdentificacion(form) {
    Object.assign(form, {
        tipo_documento: "cedula_ciudadania",
        numero_documento: "1017245880",
        genero: "femenino",
        primer_nombre: "Carolina",
        primer_apellido: "Restrepo",
        fecha_nacimiento: "1992-03-08",
        fecha_expedicion_documento: "2010-06-14",
    });

    form.nacimiento = { pais: "CO", departamento: 1, ciudad: 10 };
    form.expedicion = { pais: "CO", departamento: 1, ciudad: 10 };
}

describe("Asistente de nuevo empleado", () => {
    it("empieza en el primer paso de cuatro", () => {
        expect(montar().text()).toContain("Paso 1 de 4");
    });

    it("no pide el estado en ninguno de los pasos: lo pone el servidor", () => {
        const texto = montar().text();

        expect(texto).not.toContain("Estado del empleado");
        expect(texto).toContain("El empleado quedará");
    });

    it("no avanza con un campo obligatorio vacío y dice cuál falta", async () => {
        const componente = montar();

        await componente.find("form").trigger("submit");

        expect(componente.text()).toContain("Paso 1 de 4");
        expect(componente.vm.form.errors.primer_nombre).toBe(
            "Este dato es obligatorio.",
        );
    });

    it("avanza cuando el paso está completo", async () => {
        const componente = montar();

        llenarIdentificacion(componente.vm.form);
        await componente.vm.$nextTick();
        await componente.find("form").trigger("submit");

        expect(componente.text()).toContain("Paso 2 de 4");
    });

    it("marca opcionales el segundo nombre y el segundo apellido", () => {
        const texto = montar().text();

        expect(texto).toContain("Segundo nombre");
        expect(texto).toContain("Segundo apellido");
        expect(texto).toContain("opcional");
    });

    it("solo pide fecha de finalización en el contrato a término fijo", async () => {
        const componente = montar();

        expect(componente.text()).not.toContain("Fecha de finalización");

        componente.vm.form.tipo_contrato = "fijo";
        await componente.vm.$nextTick();

        expect(componente.text()).toContain("Fecha de finalización");
    });

    it("no pide el país de la residencia, que siempre es Colombia", () => {
        expect(montar().text()).toContain(
            "La residencia es siempre en Colombia",
        );
    });
});

describe("Perfil sociodemográfico", () => {
    // Llevar el asistente hasta el paso del perfil sin tener que llenar los
    // tres pasos anteriores: lo que se prueba aquí es el bloque, no el avance.
    function enElPaso(componente, indice) {
        componente.vm.paso = indice;

        return componente.vm.$nextTick();
    }

    it("no aparece cuando la empresa no lo habilitó", () => {
        const texto = montar().text();

        expect(texto).toContain("Paso 1 de 4");
        expect(texto).not.toContain("Perfil sociodemográfico");
    });

    it("agrega un quinto paso cuando la empresa lo habilitó", () => {
        const texto = montar({ perfilSociodemografico: true }).text();

        expect(texto).toContain("Paso 1 de 5");
        expect(texto).toContain("Perfil sociodemográfico");
    });

    it("va antes del resumen, que siempre es el último paso", () => {
        const componente = montar({ perfilSociodemografico: true });

        expect(componente.vm.indiceDelPerfil).toBe(3);
        expect(componente.vm.pasos[4].titulo).toBe("Resumen");
    });

    it("avanza al resumen sin llenar ni un campo del bloque", async () => {
        const componente = montar({ perfilSociodemografico: true });

        await enElPaso(componente, componente.vm.indiceDelPerfil);
        await componente.find("form").trigger("submit");

        expect(componente.vm.esElResumen).toBe(true);
        expect(componente.text()).toContain("Paso 5 de 5");
    });

    it("dice que todos sus campos son opcionales", async () => {
        const componente = montar({ perfilSociodemografico: true });

        await enElPaso(componente, componente.vm.indiceDelPerfil);

        expect(componente.text()).toContain(
            "Todos estos campos son opcionales.",
        );
    });

    it("en el resumen solo enseña lo que se llenó", async () => {
        const componente = montar({ perfilSociodemografico: true });

        componente.vm.form.perfil.estrato = 3;
        componente.vm.form.perfil.numero_hijos = "2";
        await enElPaso(componente, 4);

        // Se mira el resumen y no el texto de la pantalla: los pasos anteriores
        // siguen en el DOM, ocultos, con todas sus etiquetas.
        expect(componente.vm.resumenDelPerfil).toEqual([
            { etiqueta: "Número de hijos", valor: "2" },
            { etiqueta: "Estrato socioeconómico", valor: "Estrato 3" },
        ]);
        expect(componente.text()).toContain("Estrato 3");
    });

    it("en el resumen explica el bloque en blanco en vez de dejarlo vacío", async () => {
        const componente = montar({ perfilSociodemografico: true });

        await enElPaso(componente, 4);

        expect(componente.text()).toContain("Sin diligenciar");
    });
});

describe("Advertencia de reingreso", () => {
    const reingreso = {
        texto: "Esta persona ya tuvo una vinculación anterior con la empresa.",
        nota: "Usted recibirá las alertas de periodo de prueba estipuladas.",
        nombre: "Carolina Restrepo",
        vinculaciones: 1,
        reciente: true,
        detalle: "El reingreso es 20 días después de la salida anterior.",
    };

    beforeEach(() => recargar.mockClear());

    it("no advierte nada mientras el servidor no ha dicho nada", () => {
        expect(montar().text()).not.toContain("ya tuvo una vinculación");
    });

    it("le pregunta al servidor al salir del campo del documento", async () => {
        const componente = montar();

        componente.vm.form.numero_documento = "1017245880";
        await componente.vm.$nextTick();
        await campoDelDocumento(componente).trigger("blur");

        expect(recargar).toHaveBeenCalledWith({
            only: ["reingreso"],
            data: { numero_documento: "1017245880", fecha_ingreso: "" },
        });
    });

    it("no pregunta con el documento vacío", async () => {
        await campoDelDocumento(montar()).trigger("blur");

        expect(recargar).not.toHaveBeenCalled();
    });

    it("no repite la misma pregunta dos veces", async () => {
        const componente = montar();

        componente.vm.form.numero_documento = "1017245880";
        await componente.vm.$nextTick();
        await campoDelDocumento(componente).trigger("blur");
        await campoDelDocumento(componente).trigger("blur");

        expect(recargar).toHaveBeenCalledTimes(1);
    });

    it("vuelve a preguntar cuando cambia la fecha de ingreso", async () => {
        const componente = montar();

        componente.vm.form.numero_documento = "1017245880";
        await componente.vm.$nextTick();
        await campoDelDocumento(componente).trigger("blur");

        componente.vm.form.fecha_ingreso = "2026-08-20";
        await componente.vm.$nextTick();
        await campoDelDocumento(componente).trigger("blur");

        expect(recargar).toHaveBeenCalledTimes(2);
    });

    it("muestra el aviso junto al documento en cuanto llega del servidor", () => {
        const texto = montar({ reingreso }).text();

        expect(texto).toContain(reingreso.texto);
        expect(texto).toContain(reingreso.nota);
        expect(texto).toContain(
            "Reingreso dentro de los 30 días siguientes a la salida",
        );
    });
});
