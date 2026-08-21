import { router } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

/*
 * El armazón que comparten todas las pantallas de listado, del lado de la
 * interfaz. Es el gemelo de `App\Listados\Listado`, que hace lo mismo en el
 * servidor.
 *
 * Las siete pantallas del documento 3 hacen lo mismo con datos distintos: no
 * filtran nada en el navegador, sino que cada cambio vuelve a pedir la página al
 * servidor con los filtros en la dirección. Eso es lo que hace que al volver de
 * la ficha todo siga donde estaba y que el enlace se pueda copiar y compartir.
 *
 * Cada pantalla declara lo único que es suyo: su ruta, de dónde leer los
 * filtros y la página, y cuáles de sus filtros son de lista. Sus columnas, sus
 * controles y sus textos vacíos siguen en la pantalla, porque no hay nada que
 * compartir en ellos.
 */

/**
 * El valor que, en un filtro de lista, no filtra nada. Es el mismo
 * `Listado::TODOS` del servidor.
 */
export const TODOS = "todos";

/**
 * Medio segundo de espera antes de buscar: pedir la página en cada tecla
 * castiga al servidor y hace parpadear la tabla mientras se escribe un
 * apellido.
 */
export const ESPERA_DE_BUSQUEDA = 400;

/**
 * @param {object} declaracion
 * @param {string} declaracion.ruta La ruta del listado, sin barra final. De
 *   ella salen también la de la ficha —`ruta/7`— y la de la exportación.
 * @param {() => object} declaracion.filtros Los filtros tal como llegan del
 *   servidor. Se lee como función para que siga siendo reactivo.
 * @param {(() => object)=} declaracion.pagina La página del listado, de la que
 *   se toma el total del botón de exportar. **Una pantalla sin exportación no lo
 *   declara**, y entonces no tiene dirección de exportación ni texto.
 * @param {(() => boolean)=} declaracion.tieneFiltros Si hay algún filtro
 *   puesto, para que el botón diga «filtrados».
 * @param {string[]=} declaracion.deLista Los filtros cuyo valor de reposo es
 *   «todos». Los demás vuelven a vacío al quitarlos.
 */
export function useListado({
    ruta,
    filtros,
    pagina = null,
    tieneFiltros = () => false,
    deLista = [],
}) {
    // La caja de búsqueda escribe sola; el resto de controles disparan al
    // cambiar. Se sincroniza con la prop para que al quitar la etiqueta del
    // filtro la caja también se vacíe.
    const busqueda = ref(filtros().buscar ?? "");

    watch(
        () => filtros().buscar,
        (valor) => {
            busqueda.value = valor ?? "";
        },
    );

    let temporizador = null;

    function buscar(valor) {
        clearTimeout(temporizador);
        temporizador = setTimeout(
            () => pedir({ buscar: valor, page: 1 }),
            ESPERA_DE_BUSQUEDA,
        );
    }

    /*
     * Vuelve a pedir el listado con los filtros cambiados. `preserveState`
     * mantiene el foco y lo escrito; `replace` evita que cada tecla deje una
     * entrada en el historial del navegador.
     */
    function pedir(cambios) {
        router.get(
            ruta,
            { ...filtros(), ...cambios },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function ordenar(clave) {
        const mismaColumna = filtros().orden === clave;
        const direccion =
            mismaColumna && filtros().direccion === "asc" ? "desc" : "asc";

        pedir({ orden: clave, direccion, page: 1 });
    }

    /*
     * Quitar un filtro lo devuelve a su valor de reposo: los de lista, a
     * «todos»; los de texto y fecha, a vacío.
     *
     * La distinción importa y no es cosmética: el filtro de estado del listado
     * de empleados llega por defecto en «activo» y el de vencimiento de
     * contratos en «los próximos 90 días», así que mandarlos vacíos los dejaría
     * en su valor por defecto en vez de quitarlos.
     */
    function quitar(clave) {
        pedir({ [clave]: deLista.includes(clave) ? TODOS : "", page: 1 });
    }

    function limpiar() {
        router.get(ruta, {}, { preserveScroll: true, replace: true });
    }

    function abrir(fila) {
        router.visit(`${ruta}/${fila.id}`);
    }

    // La dirección de la exportación lleva los mismos filtros que la pantalla,
    // para que el archivo traiga exactamente lo que se está viendo. El tamaño
    // de página no va: la exportación no pagina.
    const urlExportar = computed(() => {
        if (pagina === null) {
            return "";
        }

        const parametros = new URLSearchParams();

        Object.entries(filtros()).forEach(([clave, valor]) => {
            if (valor !== "" && valor !== null && clave !== "porPagina") {
                parametros.append(clave, valor);
            }
        });

        return `${ruta}/exportar?${parametros.toString()}`;
    });

    // «Exportar 43 registros filtrados» (documento 3, sección 6.1): el botón
    // dice cuántos se lleva y que son los de los filtros puestos, no todos.
    // Está escrito una vez a propósito: dos copias de una frase obligatoria se
    // separan solas.
    const textoExportar = computed(() => {
        if (pagina === null) {
            return "";
        }

        const total = pagina().total;
        const uno = total === 1;
        const registros = uno ? "registro" : "registros";
        const filtrados = tieneFiltros() ? (uno ? " filtrado" : " filtrados") : "";

        return `Exportar ${total} ${registros}${filtrados}`;
    });

    return {
        busqueda,
        buscar,
        pedir,
        ordenar,
        quitar,
        limpiar,
        abrir,
        urlExportar,
        textoExportar,
    };
}
