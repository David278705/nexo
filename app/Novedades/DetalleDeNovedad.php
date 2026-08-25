<?php

namespace App\Novedades;

use App\Contratacion\FichaDeEmpleado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\OptionalProp;

/**
 * La novedad que se está editando o que se va a eliminar (documento 1, 6.2).
 *
 * Es el gemelo de `EmpleadoDeNovedad` para el otro lado del flujo: aquella
 * resuelve a quién se le registra algo nuevo, y esta resuelve qué registro ya
 * existente se está tocando. Llega por el mismo camino —una prop
 * `Inertia::optional` que la pantalla pide con `router.reload({ only: [...] })`
 * al pulsar Editar o Eliminar—, así que el listado no paga nada por ofrecer
 * dos acciones que casi nunca se usan.
 *
 * ## Por qué una sola prop sirve para editar y para eliminar
 *
 * Porque las dos acciones necesitan lo mismo: saber de qué registro se habla.
 * Editar usa `valores`, `contexto` y `datos`; eliminar usa `confirmacion`.
 * Partirla en dos props obligaría a dos consultas y a que las dos pudieran
 * discrepar sobre qué fila está seleccionada.
 *
 * ## Por qué el empleado se lee del registro y no del documento digitado
 *
 * Porque el documento 1, 6.2 dice que **cualquier** novedad se puede editar o
 * eliminar, sin límite de antigüedad, y la de una persona que ya salió de la
 * empresa también. Volver a pasar por la búsqueda de `EmpleadoDeNovedad`
 * —que exige empleado activo, y con razón, para registrar algo nuevo— dejaría
 * sin corregir precisamente las novedades más viejas.
 */
final class DetalleDeNovedad
{
    /**
     * El nombre de la prop y el del parámetro con que se pide. Es el mismo en
     * el servidor y en las cinco pantallas, y por eso es una constante.
     */
    public const PROP = 'novedad';

    /**
     * La prop que declara el `index()` de cada uno de los tres módulos.
     *
     * @param  class-string<Model&NovedadEditable>  $modelo
     */
    public static function prop(Request $peticion, string $modelo): OptionalProp
    {
        return Inertia::optional(fn (): ?array => self::para($peticion, $modelo));
    }

    /**
     * @param  class-string<Model&NovedadEditable>  $modelo
     * @return array{id: int, datos: list<array{etiqueta: string, valor: string, pendiente: bool, nota: string}>, valores: array<string, string|bool|int|null>, contexto: array<string, string>, confirmacion: string}|null
     */
    public static function para(Request $peticion, string $modelo): ?array
    {
        $id = $peticion->query(self::PROP);

        if (! is_numeric($id)) {
            return null;
        }

        /*
         * La consulta pasa por el global scope de `PerteneceATenant`, así que
         * la novedad de otra empresa no se encuentra y aquí no hay que
         * comprobar nada aparte.
         */
        $novedad = $modelo::query()->find((int) $id);

        if (! $novedad instanceof NovedadEditable) {
            return null;
        }

        $vinculacion = $novedad->vinculacionDeLaNovedad();

        return [
            'id' => (int) $novedad->getKey(),
            'datos' => FichaDeEmpleado::de($vinculacion)->resumenParaNovedad(),
            'valores' => $novedad->valoresParaEdicion(),
            'contexto' => $novedad->contextoDeEdicion(),
            'confirmacion' => $novedad->confirmacionDeBorrado(),
        ];
    }
}
