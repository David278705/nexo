<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Proveedor\CatalogosDelSistema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Las reglas de dar de alta o editar una entidad de las cinco listas: EPS,
 * fondos de pensión, ARL, cajas de compensación y bancos (documento 4,
 * capítulo 8).
 *
 * Un solo `FormRequest` para las cinco listas y para las dos acciones, porque
 * las cinco tienen exactamente la misma forma —código opcional y nombre único—
 * y separar cinco copias idénticas solo garantiza que un día una se quede atrás.
 * De qué lista se trata lo dice la dirección.
 *
 * **El nombre es único y esa unicidad ignora lo desactivado.** Una EPS que se
 * liquidó sigue en la tabla con su nombre, y volver a darla de alta como si
 * fuera nueva dejaría dos filas con el mismo nombre en los formularios el día
 * que alguien reactive la vieja. Si hace falta recuperarla, se reactiva desde la
 * misma pantalla.
 *
 * `authorize()` vuelve a preguntar por el rol aunque la ruta ya lo exija: estas
 * listas las ven todos los clientes a la vez y una ruta que un día pierda su
 * middleware por una edición distraída seguiría respondiendo 403.
 */
class GuardarEntidad extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario instanceof User && $usuario->esOwner();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $modelo = CatalogosDelSistema::modeloDe((string) $this->route('catalogo'));
        $tabla = (new $modelo)->getTable();

        return [
            'codigo' => ['nullable', 'string', 'max:20'],
            'nombre' => [
                'required',
                'string',
                'max:150',
                Rule::unique($tabla, 'nombre')->ignore($this->route('entidad')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escriba el nombre con el que la entidad se va a ofrecer.',
            'nombre.unique' => 'Esa entidad ya está en la lista. Si la ve desactivada, actívela en '
                .'vez de agregarla otra vez.',
        ];
    }

    /**
     * Los dos campos, ya validados.
     *
     * El código llega vacío cuando no se escribió, y vacío no es lo mismo que
     * ausente: la columna admite nulos porque no todas las listas tienen código
     * oficial, y una cadena vacía sería un código que no existe.
     *
     * @return array{codigo: string|null, nombre: string}
     */
    public function entidad(): array
    {
        $codigo = trim((string) $this->input('codigo', ''));

        return [
            'codigo' => $codigo === '' ? null : $codigo,
            'nombre' => trim((string) $this->input('nombre')),
        ];
    }
}
