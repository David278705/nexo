import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import TablaDatos from "./TablaDatos.vue";

const columnas = [
    { clave: "nombre", titulo: "Nombre" },
    { clave: "saldo", titulo: "Saldo", numerica: true },
];

const filas = [
    { id: 1, nombre: "Ana Torres", saldo: "17,25" },
    { id: 2, nombre: "Carlos Ramírez", saldo: "4,25" },
];

describe("TablaDatos", () => {
    it("pinta una fila por registro con sus celdas", () => {
        const componente = mount(TablaDatos, { props: { columnas, filas } });

        expect(componente.findAll("tbody tr")).toHaveLength(2);
        expect(componente.text()).toContain("Ana Torres");
        expect(componente.text()).toContain("17,25");
    });

    it("alinea a la derecha solo las columnas numéricas", () => {
        const componente = mount(TablaDatos, { props: { columnas, filas } });
        const celdas = componente.findAll("tbody tr:first-child td");

        expect(celdas[0].classes()).toContain("text-left");
        expect(celdas[1].classes()).toContain("text-right");
    });

    it("deja fijo el encabezado al hacer scroll", () => {
        const componente = mount(TablaDatos, { props: { columnas, filas } });

        expect(componente.find("thead th").classes()).toContain("sticky");
    });

    it("avisa con la fila entera al pulsarla", async () => {
        const componente = mount(TablaDatos, { props: { columnas, filas } });

        await componente.find("tbody tr").trigger("click");

        expect(componente.emitted("abrir")[0][0]).toEqual(filas[0]);
    });

    it("también se abre con el teclado", async () => {
        const componente = mount(TablaDatos, { props: { columnas, filas } });

        await componente.find("tbody tr").trigger("keydown.enter");

        expect(componente.emitted("abrir")).toHaveLength(1);
        expect(componente.find("tbody tr").attributes("tabindex")).toBe("0");
    });

    it("pide al servidor el orden al pulsar el encabezado de una columna ordenable", async () => {
        const componente = mount(TablaDatos, {
            props: {
                columnas: [
                    { clave: "nombre", titulo: "Nombre", ordenable: true },
                    { clave: "saldo", titulo: "Saldo", numerica: true },
                ],
                filas,
            },
        });

        const encabezados = componente.findAll("thead th");

        expect(encabezados[1].find("button").exists()).toBe(false);

        await encabezados[0].find("button").trigger("click");

        expect(componente.emitted("ordenar")).toEqual([["nombre"]]);
    });

    it("dice por dónde está ordenada, y no solo con una flecha", () => {
        const componente = mount(TablaDatos, {
            props: {
                columnas: [
                    { clave: "nombre", titulo: "Nombre", ordenable: true },
                    { clave: "saldo", titulo: "Saldo", ordenable: true },
                ],
                filas,
                ordenPor: "nombre",
                ordenDireccion: "desc",
            },
        });

        const encabezados = componente.findAll("thead th");

        expect(encabezados[0].attributes("aria-sort")).toBe("descending");
        expect(encabezados[1].attributes("aria-sort")).toBe("none");
    });

    it("hasta 1024px pinta una tarjeta por registro con las tres columnas más importantes", () => {
        const columnasConAcciones = [
            { clave: "nombre", titulo: "Nombre" },
            { clave: "cargo", titulo: "Cargo" },
            { clave: "inicio", titulo: "Inicio" },
            { clave: "fin", titulo: "Fin" },
            { clave: "acciones", titulo: "Acciones" },
        ];
        const filasConAcciones = [
            {
                id: 1,
                nombre: "Ana Torres",
                cargo: "Analista",
                inicio: "1 de julio",
                fin: "5 de julio",
                acciones: "Editar",
            },
        ];

        const componente = mount(TablaDatos, {
            props: { columnas: columnasConAcciones, filas: filasConAcciones },
        });

        const tarjeta = componente.find("ul li");

        expect(tarjeta.text()).toContain("Nombre");
        expect(tarjeta.text()).toContain("Ana Torres");
        expect(tarjeta.text()).toContain("Cargo");
        expect(tarjeta.text()).toContain("Analista");
        expect(tarjeta.text()).toContain("Inicio");
        expect(tarjeta.text()).toContain("1 de julio");
        // El cuarto campo, «Fin», no cabe entre los tres más importantes.
        expect(tarjeta.text()).not.toContain("Fin");
    });

    it("en la tarjeta la columna de acciones siempre se ve, no cuenta entre las tres", () => {
        const columnasConAcciones = [
            { clave: "nombre", titulo: "Nombre" },
            { clave: "cargo", titulo: "Cargo" },
            { clave: "inicio", titulo: "Inicio" },
            { clave: "acciones", titulo: "Acciones" },
        ];
        const filasConAcciones = [
            {
                id: 1,
                nombre: "Ana Torres",
                cargo: "Analista",
                inicio: "1 de julio",
                acciones: "Editar",
            },
        ];

        const componente = mount(TablaDatos, {
            props: { columnas: columnasConAcciones, filas: filasConAcciones },
        });

        expect(componente.find("ul li").text()).toContain("Editar");
    });

    it("la tarjeta también avisa al pulsarla cuando la fila es clicable", async () => {
        const componente = mount(TablaDatos, { props: { columnas, filas } });

        await componente.find("ul li > div").trigger("click");

        expect(componente.emitted("abrir")[0][0]).toEqual(filas[0]);
    });

    it("la tabla solo se ve desde 1024px y la tarjeta solo por debajo", () => {
        const componente = mount(TablaDatos, { props: { columnas, filas } });

        expect(componente.find("ul").classes()).toContain("lg:hidden");
        expect(componente.find("table").element.parentElement.className).toContain(
            "lg:block",
        );
    });

    it("sin registros muestra el estado vacío y no la palabra «no hay datos»", () => {
        const componente = mount(TablaDatos, {
            props: {
                columnas,
                filas: [],
                tituloVacio: "Aún no has registrado incapacidades",
            },
        });

        expect(componente.find("table").exists()).toBe(false);
        expect(componente.text()).toContain(
            "Aún no has registrado incapacidades",
        );
        expect(componente.text().toLowerCase()).not.toContain("no hay datos");
    });
});
