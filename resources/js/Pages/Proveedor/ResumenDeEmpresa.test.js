import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const pagina = {
    url: "/proveedor/empresas/1/resumen",
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
}));

const ResumenDeEmpresa = (await import("./ResumenDeEmpresa.vue")).default;

const texto = [
    "Esto es lo que quedó configurado",
    "",
    "Razón social: Distribuidora El Progreso S.A.S.",
    "Sábado laboral: Sí, el sábado es día laboral",
].join("\n");

const props = {
    empresa: { nombre: "Distribuidora El Progreso S.A.S." },
    titulo: "Esto es lo que quedó configurado",
    renglones: [
        { etiqueta: "Razón social", valor: "Distribuidora El Progreso S.A.S." },
        { etiqueta: "Sábado laboral", valor: "Sí, el sábado es día laboral" },
        { etiqueta: "Correo", valor: "ana@elprogreso.com" },
    ],
    texto,
    certificado: null,
    volver: "/proveedor/empresas",
};

function montar(cambios = {}) {
    return mount(ResumenDeEmpresa, { props: { ...props, ...cambios } });
}

describe("el resumen de la empresa creada", () => {
    it("enseña los renglones que armó el servidor", () => {
        const pintado = montar().text();

        expect(pintado).toContain("Esto es lo que quedó configurado");
        expect(pintado).toContain("Distribuidora El Progreso S.A.S.");
        expect(pintado).toContain("Sí, el sábado es día laboral");
        expect(pintado).toContain("ana@elprogreso.com");
    });

    it("copia exactamente el mismo texto que enseña", async () => {
        const escribir = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal("navigator", { clipboard: { writeText: escribir } });

        const pantalla = montar();

        const copiar = pantalla
            .findAll("button")
            .find((boton) => boton.text().includes("Copiar el resumen"));

        await copiar.trigger("click");

        // El portapapeles devuelve una promesa: sin dejar pasar el turno, el
        // aviso de «copiado» todavía no se ha pintado.
        await new Promise((seguir) => setTimeout(seguir, 0));
        await pantalla.vm.$nextTick();

        expect(escribir).toHaveBeenCalledWith(texto);
        expect(pantalla.text()).toContain("Copiado al portapapeles.");

        vi.unstubAllGlobals();
    });

    it("solo ofrece el certificado cuando quedó adjunto", () => {
        expect(montar().find("a[href='/descarga']").exists()).toBe(false);

        const conCertificado = montar({ certificado: { url: "/descarga" } });

        expect(conCertificado.find("a[href='/descarga']").exists()).toBe(true);
    });
});
