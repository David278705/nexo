<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Un plan del catálogo: nombre, precio mensual y los dos cupos incluidos
 * (documento 4, capítulo 6).
 *
 * **No hay ningún valor por omisión, y menos el precio.** La tabla de precios es
 * la decisión A.1 del documento 6 y sigue aplazada a propósito: proponer aquí
 * una cifra sería tomarla en un `FormRequest`. Los cuatro campos se escriben.
 */
class GuardarPlan extends FormRequest
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
        $plan = $this->route('plan');

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('planes', 'nombre')
                    ->ignore(is_object($plan) ? $plan : null)
                    ->whereNull('deleted_at'),
            ],
            // El precio en pesos, con centavos permitidos pero no exigidos.
            'precio_mensual' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'empleados_incluidos' => ['required', 'integer', 'min:1'],
            'usuarios_incluidos' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escriba cómo se llama el plan.',
            'nombre.unique' => 'Ya hay un plan con ese nombre.',
            'precio_mensual.required' => 'Escriba el valor mensual del plan.',
            'empleados_incluidos.required' => 'Escriba cuántos empleados incluye.',
            'empleados_incluidos.min' => 'Un plan que no incluye ningún empleado no se puede contratar.',
            'usuarios_incluidos.required' => 'Escriba cuántos usuarios administradores incluye.',
            'usuarios_incluidos.min' => 'Sin al menos un usuario nadie podría entrar a la empresa.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function plan(): array
    {
        return [
            'nombre' => (string) $this->input('nombre'),
            'precio_mensual' => (float) $this->input('precio_mensual'),
            'empleados_incluidos' => $this->integer('empleados_incluidos'),
            'usuarios_incluidos' => $this->integer('usuarios_incluidos'),
        ];
    }
}
