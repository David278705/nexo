<script setup>
import { computed } from "vue";
import Selector from "@/Components/Selector.vue";
import CampoTexto from "@/Components/CampoTexto.vue";
import Aviso from "@/Components/Aviso.vue";

/*
 * La regla 5.1.1 del documento 1, en un solo sitio.
 *
 * El país siempre sale de una lista. Si es Colombia, el departamento y la
 * ciudad salen de listas encadenadas —al elegir el departamento se cargan sus
 * ciudades— y se guarda el identificador del catálogo. Si el país es otro, los
 * dos campos se digitan como texto libre: no se construyen listas de ciudades
 * del mundo entero, y lo que el cliente escriba a mano queda bajo su
 * responsabilidad.
 *
 * Se usa tres veces en el formulario de empleado —nacimiento, expedición del
 * documento y residencia—, y por eso es un componente y no tres bloques
 * parecidos. La residencia pasa `soloColombia`: un contrato laboral colombiano
 * solo se celebra con quien reside en el país, así que ahí no hay que
 * preguntarlo.
 */
const props = defineProps({
    // `{ pais, departamento, ciudad }`. En Colombia, `departamento` y `ciudad`
    // son identificadores del catálogo; fuera de Colombia, texto libre.
    modelValue: {
        type: Object,
        default: () => ({ pais: "", departamento: "", ciudad: "" }),
    },
    paises: { type: Array, default: () => [] },
    // Cada uno es `{ valor, texto, ciudades: [{ valor, texto }] }`.
    departamentos: { type: Array, default: () => [] },
    codigoDeColombia: { type: String, required: true },
    // Completa las etiquetas: «de nacimiento», «de residencia».
    sufijo: { type: String, default: "" },
    soloColombia: { type: Boolean, default: false },
    errores: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

function etiqueta(campo) {
    return props.sufijo ? `${campo} ${props.sufijo}` : campo;
}

const esColombia = computed(
    () => props.soloColombia || props.modelValue.pais === props.codigoDeColombia,
);

const departamentoElegido = computed(() =>
    props.departamentos.find(
        (departamento) =>
            String(departamento.valor) === String(props.modelValue.departamento),
    ),
);

// Vacío mientras no haya departamento: ofrecer los mil cien municipios del país
// de una vez es lo que la lista encadenada existe para evitar.
const ciudades = computed(() => departamentoElegido.value?.ciudades ?? []);

/*
 * Bogotá no está entre los municipios de Cundinamarca (D.7, HD-07): el DANE la
 * define como Distrito Capital, un departamento propio. El catálogo está bien
 * así y no se toca; lo que hace falta es que quien busca Cundinamarca porque
 * nació o vive en Bogotá no se quede mirando una lista donde no está.
 */
const avisaCundinamarca = computed(
    () => esColombia.value && departamentoElegido.value?.texto === "Cundinamarca",
);

function cambiar(cambios) {
    emit("update:modelValue", { ...props.modelValue, ...cambios });
}

/*
 * Cambiar el país deja sin sentido lo que hubiera debajo: un departamento de
 * Colombia con el país en Perú, o un texto libre donde ahora va una lista. Se
 * limpian los dos, y lo mismo hace el departamento con la ciudad.
 */
function cambiarPais(pais) {
    cambiar({ pais, departamento: "", ciudad: "" });
}

function cambiarDepartamento(departamento) {
    cambiar({ departamento, ciudad: "" });
}
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-3">
        <Selector
            v-if="!soloColombia"
            :model-value="modelValue.pais"
            :etiqueta="etiqueta('País')"
            :opciones="paises"
            :error="errores.pais ?? ''"
            @update:model-value="cambiarPais"
        />

        <template v-if="esColombia">
            <Selector
                :model-value="modelValue.departamento"
                :etiqueta="etiqueta('Departamento')"
                :opciones="departamentos"
                :error="errores.departamento ?? ''"
                @update:model-value="cambiarDepartamento"
            />

            <Selector
                :model-value="modelValue.ciudad"
                :etiqueta="etiqueta('Ciudad')"
                :opciones="ciudades"
                :marcador="
                    departamentoElegido
                        ? 'Seleccione…'
                        : 'Elija primero el departamento'
                "
                :error="errores.ciudad ?? ''"
                @update:model-value="(ciudad) => cambiar({ ciudad })"
            />

            <Aviso v-if="avisaCundinamarca" class="sm:col-span-3">
                Bogotá no está en esta lista: es su propio departamento, no un
                municipio de Cundinamarca. Búsquela como «Bogotá D.C.» en el
                selector de departamento.
            </Aviso>
        </template>

        <template v-else>
            <CampoTexto
                :model-value="modelValue.departamento"
                :etiqueta="etiqueta('Departamento')"
                ayuda="Se digita: fuera de Colombia no hay lista."
                :error="errores.departamento ?? ''"
                @update:model-value="
                    (departamento) => cambiar({ departamento })
                "
            />

            <CampoTexto
                :model-value="modelValue.ciudad"
                :etiqueta="etiqueta('Ciudad')"
                :error="errores.ciudad ?? ''"
                @update:model-value="(ciudad) => cambiar({ ciudad })"
            />
        </template>
    </div>
</template>
