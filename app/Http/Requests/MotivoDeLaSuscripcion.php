<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El motivo de una suspensión o de una reactivación (documento 4, capítulo 6:
 * «suspensión y reactivación, con motivo y constancia»).
 *
 * Un solo `FormRequest` para las dos acciones porque las dos piden lo mismo: una
 * línea escrita por quien la ejecuta. **No hay lista de motivos ni motivo por
 * omisión**: los motivos de una suspensión —mora, cierre de la empresa, un
 * acuerdo de pago incumplido— no son una lista cerrada, y un motivo por omisión
 * es un motivo que nadie escribió.
 */
class MotivoDeLaSuscripcion extends FormRequest
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
            // Un mínimo que no admita «ok» ni «x»: esto lo va a leer el cliente
            // en su propia auditoría dentro de seis meses.
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Escriba el motivo. Queda como constancia y el cliente lo puede ver.',
            'motivo.min' => 'Escriba un motivo que se entienda dentro de seis meses.',
        ];
    }

    public function motivo(): string
    {
        return trim((string) $this->input('motivo'));
    }
}
