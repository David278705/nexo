<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Las reglas de agregar o corregir un municipio (documento 4, capítulo 8:
 * «departamentos y ciudades de Colombia, carga inicial y actualización
 * ocasional»).
 *
 * El municipio es único **dentro de su departamento** y no en todo el país: hay
 * un Santa Rosa en varios departamentos y son municipios distintos. Es la misma
 * regla que ya tiene la tabla.
 *
 * El código DANE es opcional porque la lista se cargó por nombre y los códigos
 * se completan cuando se cargue el archivo oficial. Cuando se escribe son cinco
 * dígitos: los dos del departamento y los tres del municipio.
 */
class GuardarCiudad extends FormRequest
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
        return [
            'departamento_id' => ['required', 'integer', Rule::exists('departamentos', 'id')],
            'nombre' => [
                'required',
                'string',
                'max:120',
                Rule::unique('ciudades', 'nombre')
                    ->where('departamento_id', (int) $this->input('departamento_id'))
                    ->ignore($this->route('ciudad')),
            ],
            'codigo_dane' => [
                'nullable',
                'digits:5',
                Rule::unique('ciudades', 'codigo_dane')->ignore($this->route('ciudad')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ese municipio ya está en ese departamento. Si lo ve desactivado, '
                .'actívelo en vez de agregarlo otra vez.',
            'codigo_dane.digits' => 'El código DANE de un municipio son cinco dígitos: los dos del '
                .'departamento y los tres del municipio.',
        ];
    }

    /**
     * @return array{departamento_id: int, nombre: string, codigo_dane: string|null}
     */
    public function ciudad(): array
    {
        $codigo = trim((string) $this->input('codigo_dane', ''));

        return [
            'departamento_id' => (int) $this->input('departamento_id'),
            'nombre' => trim((string) $this->input('nombre')),
            'codigo_dane' => $codigo === '' ? null : $codigo,
        ];
    }
}
