<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El cambio de plan de una suscripción, «con la fecha desde la que aplica»
 * (documento 4, capítulo 6).
 *
 * La fecha es obligatoria y no es «hoy» por omisión: se puede acordar hoy un
 * plan que arranca el mes entrante, y esa fecha es la que decide desde cuándo se
 * factura el valor nuevo.
 */
class CambiarDePlan extends FormRequest
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
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('planes', 'id')->where('activo', true)->whereNull('deleted_at'),
            ],
            'aplica_desde' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'Elija el plan nuevo.',
            'plan_id.exists' => 'Ese plan no existe o está desactivado.',
            'aplica_desde.required' => 'Escriba desde cuándo aplica el plan nuevo.',
        ];
    }
}
