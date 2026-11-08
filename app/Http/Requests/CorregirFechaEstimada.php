<?php

namespace App\Http\Requests;

use App\Contratacion\TipoContrato;
use App\Models\Vinculacion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Corregir la fecha de finalización estimada de una obra o labor (`PROD-23`,
 * decidido por David el 2026-11-08).
 *
 * Existe porque la estimación es del cliente y puede cambiar: la obra se alarga
 * o se acorta. El periodo de prueba se recalcula solo, porque `PeriodoDePrueba`
 * no guarda nada. Se puede dejar vacía, que es decir que ya no hay estimación y
 * por tanto tampoco periodo de prueba.
 *
 * Solo en una obra o labor **vigente**: en otra modalidad, o en una que ya pasó a
 * indefinido, la acción no existe y responde 403.
 */
class CorregirFechaEstimada extends FormRequest
{
    public function authorize(): bool
    {
        $contrato = $this->route('contrato');

        return $contrato instanceof Vinculacion && $contrato->tipo_contrato === TipoContrato::ObraLabor;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Vinculacion $contrato */
        $contrato = $this->route('contrato');

        return [
            'fecha_fin_estimada' => ['nullable', 'date', 'after:'.$contrato->fecha_ingreso->toDateString()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_fin_estimada.after' => 'La fecha de finalización estimada tiene que ser posterior a la de ingreso.',
        ];
    }
}
