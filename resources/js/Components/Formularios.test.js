import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import CampoTexto from "./CampoTexto.vue";
import CampoFecha from "./CampoFecha.vue";
import Boton from "./Boton.vue";
import Etiqueta from "./Etiqueta.vue";
import Aviso from "./Aviso.vue";
import EstadoVacio from "./EstadoVacio.vue";
import TarjetaIndicador from "./TarjetaIndicador.vue";
import BarraFiltros from "./BarraFiltros.vue";

describe("CampoTexto", () => {
    it("tiene etiqueta real asociada al campo, no solo marca de agua", () => {
        const componente = mount(CampoTexto, {
            props: { etiqueta: "Documento" },
        });

        expect(componente.find("label").text()).toContain("Documento");
        expect(componente.find("label").attributes("for")).toBe(
            componente.find("input").attributes("id"),
        );
    });

    it("marca los campos opcionales, no los obligatorios", () => {
        const componente = mount(CampoTexto, {
            props: { etiqueta: "Segundo apellido", opcional: true },
        });

        expect(componente.find("label").text()).toContain("(opcional)");
    });

    it("el error reemplaza al texto de ayuda", () => {
        const componente = mount(CampoTexto, {
            props: {
                etiqueta: "Documento",
                ayuda: "Sin puntos ni comas.",
                error: "El documento 123 no corresponde a ningún empleado activo.",
            },
        });

        expect(componente.text()).not.toContain("Sin puntos ni comas.");
        expect(componente.text()).toContain("El documento 123");
        expect(componente.find("input").attributes("aria-invalid")).toBe(
            "true",
        );
    });

    it("valida al salir del campo", async () => {
        const componente = mount(CampoTexto, {
            props: { etiqueta: "Documento" },
        });

        await componente.find("input").trigger("blur");

        expect(componente.emitted("salir")).toHaveLength(1);
    });
});

describe("CampoFecha", () => {
    it("usa el control de fecha del navegador", () => {
        const componente = mount(CampoFecha, {
            props: { etiqueta: "Fecha de ingreso", modelValue: "2026-04-15" },
        });

        expect(componente.find("input").attributes("type")).toBe("date");
        expect(componente.find("input").element.value).toBe("2026-04-15");
    });

    it("avisa del valor escrito", async () => {
        const componente = mount(CampoFecha, {
            props: { etiqueta: "Fecha de ingreso" },
        });

        await componente.find("input").setValue("2026-05-14");

        expect(componente.emitted("update:modelValue")[0]).toEqual([
            "2026-05-14",
        ]);
    });
});

describe("Boton", () => {
    it("tiene las cuatro variantes del documento", () => {
        const primario = mount(Boton, { props: { variante: "primario" } });
        const secundario = mount(Boton, { props: { variante: "secundario" } });
        const texto = mount(Boton, { props: { variante: "texto" } });
        const destructivo = mount(Boton, {
            props: { variante: "destructivo" },
        });

        expect(primario.classes()).toContain("bg-accion");
        expect(secundario.classes()).toContain("bg-superficie");
        expect(texto.classes()).toContain("bg-transparent");
        expect(destructivo.classes()).toContain("bg-error");
    });

    it("se deshabilita mientras envía", () => {
        const componente = mount(Boton, { props: { enviando: true } });

        expect(componente.attributes("disabled")).toBeDefined();
    });

    it("muestra un indicador de carga mientras envía, no solo el botón atenuado", () => {
        const quieto = mount(Boton, {
            props: { enviando: false },
            slots: { default: "Guardar" },
        });
        const enviando = mount(Boton, {
            props: { enviando: true },
            slots: { default: "Guardar" },
        });

        expect(quieto.find(".animate-spin").exists()).toBe(false);
        expect(enviando.find(".animate-spin").exists()).toBe(true);
        // El texto sigue puesto: el círculo se agrega, no lo reemplaza.
        expect(enviando.text()).toBe("Guardar");
    });
});

describe("Etiqueta", () => {
    it("dice el estado con palabras además de con color", () => {
        const componente = mount(Etiqueta, {
            props: { tono: "advertencia" },
            slots: { default: "Por vencer" },
        });

        expect(componente.text()).toBe("Por vencer");
        expect(componente.classes()).toContain("bg-advertencia-fondo");
    });
});

describe("Aviso", () => {
    it("el error se anuncia como alerta y el resto como estado", () => {
        const error = mount(Aviso, {
            props: { tono: "error" },
            slots: { default: "Falta el documento." },
        });
        const informacion = mount(Aviso, {
            slots: { default: "El periodo de prueba termina el 14 de mayo." },
        });

        expect(error.attributes("role")).toBe("alert");
        expect(informacion.attributes("role")).toBe("status");
    });

    it("lleva el tono en palabras aunque no tenga título visible", () => {
        const componente = mount(Aviso, {
            props: { tono: "advertencia" },
            slots: { default: "Faltan 1,25 días." },
        });

        expect(componente.find(".sr-only").text()).toBe("Atención:");
    });
});

describe("EstadoVacio", () => {
    it("explica la sección y ofrece la primera acción", () => {
        const componente = mount(EstadoVacio, {
            props: {
                titulo: "Aún no has registrado incapacidades",
                descripcion: "Aquí quedan las incapacidades de tu equipo.",
            },
            slots: { default: "<button>Registrar incapacidad</button>" },
        });

        expect(componente.text()).toContain(
            "Aún no has registrado incapacidades",
        );
        expect(componente.find("button").text()).toBe(
            "Registrar incapacidad",
        );
    });
});

describe("TarjetaIndicador", () => {
    it("muestra la cifra, la etiqueta y la variación con flecha", () => {
        const componente = mount(TarjetaIndicador, {
            props: {
                valor: "3",
                etiqueta: "Contratos por vencer",
                variacion: "2 más que el mes pasado",
                tendencia: "sube",
            },
        });

        expect(componente.text()).toContain("Contratos por vencer");
        expect(componente.text()).toContain("▲ 2 más que el mes pasado");
    });

    it("sin variación no inventa una", () => {
        const componente = mount(TarjetaIndicador, {
            props: { valor: "24", etiqueta: "Empleados activos" },
        });

        expect(componente.text()).toBe("24Empleados activos");
    });
});

describe("BarraFiltros", () => {
    it("muestra los filtros aplicados como etiquetas removibles", async () => {
        const componente = mount(BarraFiltros, {
            props: {
                aplicados: [{ clave: "estado", texto: "Estado: Activo" }],
            },
        });

        expect(componente.text()).toContain("Estado: Activo");

        await componente
            .find('button[aria-label="Quitar el filtro Estado: Activo"]')
            .trigger("click");

        expect(componente.emitted("quitar")[0]).toEqual(["estado"]);
    });

    it("sin filtros aplicados no muestra la línea de etiquetas", () => {
        const componente = mount(BarraFiltros);

        expect(componente.text()).not.toContain("Filtros aplicados");
    });
});
