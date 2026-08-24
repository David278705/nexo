import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { h, nextTick } from "vue";

const reload = vi.fn();

/*
 * Del enrutador de Inertia solo interesa qué se le pide y con qué: la biblioteca
 * ya está probada por su autor.
 */
vi.mock("@inertiajs/vue3", async (importarOriginal) => ({
    ...(await importarOriginal()),
    router: { reload },
}));

const ModalDeNovedad = (await import("./ModalDeNovedad.vue")).default;

const flujo = {
    etiqueta: "Número de documento del empleado",
    ayuda: "El empleado debe estar registrado y activo. Si acaba de ingresar y aún no está en el sistema, regístralo primero.",
};

const encontrada = {
    documento: "43887201",
    encontrado: true,
    error: null,
    vinculacion: 7,
    nombre: "Carolina Ramírez Gómez",
    datos: [
        { etiqueta: "Empleado", valor: "Carolina Ramírez Gómez", pendiente: false, nota: "" },
        { etiqueta: "Documento", valor: "43887201", pendiente: false, nota: "" },
        { etiqueta: "Cargo", valor: "Analista de compras", pendiente: false, nota: "" },
        { etiqueta: "Fecha de ingreso", valor: "15/01/2024", pendiente: false, nota: "" },
        { etiqueta: "Saldo de vacaciones", valor: "12,75 días", pendiente: false, nota: "" },
    ],
};

const noExiste = {
    documento: "99999999",
    encontrado: false,
    error: "El documento 99999999 no corresponde a ningún empleado activo. Verifica el número o registra primero al empleado.",
    vinculacion: null,
    nombre: null,
    datos: [],
};

/*
 * Una novedad de prueba: los campos que un módulo pone en el `slot`. Es lo que
 * hará el módulo de incapacidades (F2-04) con sus fechas y su diagnóstico.
 */
function montar(props = {}) {
    return mount(ModalDeNovedad, {
        props: {
            abierto: true,
            titulo: "Nueva incapacidad",
            flujo,
            empleado: null,
            ...props,
        },
        slots: {
            default: ({ empleado }) =>
                h("p", `Registrando una incapacidad a ${empleado?.nombre ?? "nadie"}`),
        },
        attachTo: document.body,
    });
}

/**
 * El campo del documento, que viaja a `body` por el `Teleport` del modal.
 */
function escribir(valor) {
    const campo = document.body.querySelector("input");
    campo.value = valor;
    campo.dispatchEvent(new Event("input"));

    return campo;
}

function boton(texto) {
    return [...document.body.querySelectorAll("button")].find(
        (candidato) => candidato.textContent.trim() === texto,
    );
}

describe("el flujo compartido de novedades", () => {
    beforeEach(() => {
        reload.mockClear();
        document.body.innerHTML = "";
    });

    it("empieza pidiendo solo el número de documento", () => {
        const componente = montar();

        expect(document.body.textContent).toContain(
            "Número de documento del empleado",
        );
        expect(document.body.textContent).toContain(
            "El empleado debe estar registrado y activo",
        );
        // Ni la ficha ni el formulario del módulo asoman todavía.
        expect(document.body.textContent).not.toContain("Registrando una incapacidad");
        expect(document.body.querySelectorAll("input")).toHaveLength(1);

        componente.unmount();
    });

    it("lleva el mismo título en los dos pasos", async () => {
        const componente = montar({ empleado: encontrada });

        expect(document.body.textContent).toContain("Nueva incapacidad");

        escribir("43887201");
        await nextTick();
        boton("Continuar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(document.body.textContent).toContain("Nueva incapacidad");

        componente.unmount();
    });

    it("pregunta al servidor al salir del campo, no al pulsar Continuar", async () => {
        const componente = montar();

        const campo = escribir("43887201");
        await nextTick();

        expect(reload).not.toHaveBeenCalled();

        campo.dispatchEvent(new Event("blur"));
        await nextTick();

        expect(reload).toHaveBeenCalledTimes(1);
        expect(reload.mock.calls[0][0].only).toEqual(["empleadoDeNovedad"]);
        expect(reload.mock.calls[0][0].data).toEqual({ documento: "43887201" });

        componente.unmount();
    });

    it("no vuelve a preguntar por el documento que ya preguntó", async () => {
        const componente = montar();
        const campo = escribir("43887201");
        await nextTick();

        campo.dispatchEvent(new Event("blur"));
        campo.dispatchEvent(new Event("blur"));
        await nextTick();

        expect(reload).toHaveBeenCalledTimes(1);

        componente.unmount();
    });

    it("no pregunta nada con el campo vacío", async () => {
        const componente = montar();

        escribir("   ").dispatchEvent(new Event("blur"));
        await nextTick();

        expect(reload).not.toHaveBeenCalled();

        componente.unmount();
    });

    it("enseña el error del servidor debajo del campo y no deja continuar", async () => {
        const componente = montar({ empleado: noExiste });

        escribir("99999999");
        await nextTick();

        expect(document.body.textContent).toContain(
            "no corresponde a ningún empleado activo",
        );
        expect(boton("Continuar").disabled).toBe(true);

        boton("Continuar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(document.body.textContent).not.toContain("Registrando una incapacidad");

        componente.unmount();
    });

    it("reemplaza el modal pequeño por el grande con la ficha y el formulario", async () => {
        const componente = montar({ empleado: encontrada });

        escribir("43887201");
        await nextTick();

        expect(boton("Continuar").disabled).toBe(false);

        boton("Continuar").dispatchEvent(new Event("click"));
        await nextTick();

        // La ficha del paso 4: nombre, cargo, fecha de ingreso y saldo.
        expect(document.body.textContent).toContain("Carolina Ramírez Gómez");
        expect(document.body.textContent).toContain("Analista de compras");
        expect(document.body.textContent).toContain("12,75 días");
        // Y debajo, el formulario del módulo, con el empleado ya resuelto.
        expect(document.body.textContent).toContain(
            "Registrando una incapacidad a Carolina Ramírez Gómez",
        );
        // El campo del documento ya no está: el modal pequeño se reemplazó.
        expect(document.body.textContent).not.toContain(
            "Número de documento del empleado",
        );

        expect(componente.emitted("seleccionado")[0][0].vinculacion).toBe(7);

        componente.unmount();
    });

    it("no da por bueno un empleado que ya no corresponde al documento del campo", async () => {
        const componente = montar({ empleado: encontrada });

        // Se borra una cifra después de la consulta: la ficha anterior no puede
        // quedarse en pantalla ni dejar continuar.
        escribir("4388720");
        await nextTick();

        expect(boton("Continuar").disabled).toBe(true);

        componente.unmount();
    });

    it("mueve el foco al modal grande al pasar del documento al formulario", async () => {
        const componente = montar({ empleado: encontrada });

        escribir("43887201");
        await nextTick();
        boton("Continuar").dispatchEvent(new Event("click"));
        // Dos vueltas de `nextTick`: una para que el modal pequeño se
        // desmonte y el grande se monte, y otra para que `Modal` ponga el
        // foco dentro de su propio diálogo (documento 3, sección 3.5). Sin
        // esto el foco se pierde en el `body` y quien registra con el
        // teclado no puede seguir completando la novedad.
        await nextTick();
        await nextTick();

        const dialogos = document.body.querySelectorAll('[role="dialog"]');
        expect(dialogos).toHaveLength(1);
        expect(dialogos[0].getAttribute("aria-label")).toBe("Nueva incapacidad");
        expect(dialogos[0].contains(document.activeElement)).toBe(true);

        componente.unmount();
    });

    it("avisa al módulo que hay que guardar, con el empleado", async () => {
        const componente = montar({ empleado: encontrada });

        escribir("43887201");
        await nextTick();
        boton("Continuar").dispatchEvent(new Event("click"));
        await nextTick();

        boton("Guardar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(componente.emitted("guardar")[0][0].vinculacion).toBe(7);

        componente.unmount();
    });

    it("no deja guardar mientras el módulo lo tenga bloqueado", async () => {
        const componente = montar({ empleado: encontrada, deshabilitado: true });

        escribir("43887201");
        await nextTick();
        boton("Continuar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(boton("Guardar").disabled).toBe(true);

        boton("Guardar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(componente.emitted("guardar")).toBeUndefined();

        componente.unmount();
    });

    it("deshabilita el guardado mientras el módulo envía", async () => {
        const componente = montar({ empleado: encontrada, enviando: true });

        escribir("43887201");
        await nextTick();
        boton("Continuar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(boton("Guardar").disabled).toBe(true);

        componente.unmount();
    });

    it("vuelve al primer paso cuando se cierra y se abre otra vez", async () => {
        const componente = montar({ empleado: encontrada });

        escribir("43887201");
        await nextTick();
        boton("Continuar").dispatchEvent(new Event("click"));
        await nextTick();

        await componente.setProps({ abierto: false });
        await componente.setProps({ abierto: true });

        expect(document.body.textContent).toContain(
            "Número de documento del empleado",
        );
        expect(document.body.textContent).not.toContain("Analista de compras");
        expect(document.body.querySelector("input").value).toBe("");

        componente.unmount();
    });
});

describe("el mismo modal en modo edición (documento 1, 6.2)", () => {
    beforeEach(() => {
        reload.mockClear();
        document.body.innerHTML = "";
    });

    // El detalle que manda `App\Novedades\DetalleDeNovedad`: la ficha del
    // empleado ya resuelta, sin pasar por la búsqueda del documento.
    const enEdicion = { id: 12, datos: encontrada.datos, valores: {} };

    it("se salta el paso del documento y abre directamente el formulario", () => {
        const componente = montar({
            titulo: "Editar incapacidad",
            edicion: enEdicion,
        });

        expect(document.body.textContent).toContain("Editar incapacidad");
        expect(document.body.textContent).toContain("Carolina Ramírez Gómez");
        expect(document.body.textContent).toContain("Analista de compras");
        expect(document.body.textContent).not.toContain(
            "Número de documento del empleado",
        );
        // Y no pregunta nada al servidor: el empleado ya se sabe.
        expect(reload).not.toHaveBeenCalled();

        componente.unmount();
    });

    it("guarda sobre el registro que ya existe", async () => {
        const componente = montar({
            titulo: "Editar incapacidad",
            edicion: enEdicion,
        });

        boton("Guardar").dispatchEvent(new Event("click"));
        await nextTick();

        expect(componente.emitted("guardar")[0][0].id).toBe(12);

        componente.unmount();
    });
});
