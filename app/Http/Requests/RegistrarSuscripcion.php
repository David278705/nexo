<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Proveedor\MedioDePago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Abrirle suscripción a una empresa que todavía no tiene ninguna (documento 4,
 * capítulo 6).
 *
 * Las dos fechas se piden por separado y ninguna se deduce de la otra: «desde
 * cuándo es cliente de pago» y «cuándo es el próximo cobro» no tienen por qué
 * ser el mismo día, y deducir una de la otra sería inventar una política de
 * facturación que no está escrita en ninguna parte.
 *
 * `unique` sobre `tenant_id` es la misma regla que el índice parcial de la
 * migración, dicha con palabras: una empresa no puede tener dos suscripciones
 * vivas. Aquí sirve para que el error se lea, no para reemplazar al índice.
 */
class RegistrarSuscripcion extends FormRequest
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
            'tenant_id' => [
                'required',
                'integer',
                Rule::exists('tenants', 'id')->whereNull('deleted_at'),
                Rule::unique('suscripciones', 'tenant_id')->whereNull('deleted_at'),
            ],
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('planes', 'id')->where('activo', true)->whereNull('deleted_at'),
            ],
            'inicia_el' => ['required', 'date'],
            'proxima_fecha_cobro' => ['required', 'date'],
            'medio_de_pago' => ['required', Rule::in(MedioDePago::valores())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tenant_id.required' => 'Elija la empresa a la que se le abre la suscripción.',
            'tenant_id.unique' => 'Esa empresa ya tiene suscripción. Su plan se cambia desde su ficha.',
            'plan_id.required' => 'Elija el plan contratado.',
            'plan_id.exists' => 'Ese plan no existe o está desactivado.',
            'inicia_el.required' => 'Escriba desde cuándo es cliente de pago.',
            'proxima_fecha_cobro.required' => 'Escriba la fecha del próximo cobro.',
            'medio_de_pago.required' => 'Diga con qué medio va a pagar.',
        ];
    }
}
