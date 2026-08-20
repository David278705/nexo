<?php

namespace App\Http\Requests;

use App\Contratacion\EventoContractual;
use App\Contratacion\RenovacionDeContrato;
use App\Models\Vinculacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Las reglas de la acción «Renovar contrato» (documento 1, 5.5).
 *
 * Son tres y ninguna es cosmética:
 *
 * - **Solo se renueva un término fijo.** Es lo que decide `authorize()`: en un
 *   indefinido o en una obra o labor la acción no existe, así que enviar el
 *   formulario a mano responde 403 y no un error de validación. El indefinido no
 *   vence y la obra o labor termina con la obra.
 * - **El tipo de prórroga es obligatorio y solo puede ser una de las dos.** Es
 *   la decisión D.1 del documento 6, resuelta como allí se propone: sin saber si
 *   la prórroga es pactada por escrito o automática por silencio, el conteo de
 *   prórrogas cortas da el número equivocado en uno de los dos casos.
 * - **La fecha de finalización tiene que ser posterior al vencimiento vigente.**
 *   Una prórroga extiende el contrato; una fecha anterior o igual no lo extiende,
 *   y si la fecha estaba mal digitada lo que corresponde es corregirla, que es
 *   otra acción.
 *
 * Lo que aquí **no** se valida es el tope de cuatro años ni el límite de
 * prórrogas cortas. El modal los enseña con todas las letras, pero convertirlos
 * en bloqueo es F1-10 y depende de las decisiones B.1 y B.3.
 */
class RenovarContrato extends FormRequest
{
    public function authorize(): bool
    {
        return RenovacionDeContrato::de($this->contrato()->historial())->disponible();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vencimiento = $this->contrato()->historial()->fechaDeVencimiento();

        return [
            'tipo' => ['required', Rule::in(EventoContractual::valoresDeProrroga())],
            'fecha_fin' => [
                'required',
                'date',
                ...$vencimiento instanceof Carbon ? ['after:'.$vencimiento->toDateString()] : [],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo' => 'tipo de prórroga',
            'fecha_fin' => 'nueva fecha de finalización',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_fin.after' => 'La nueva fecha de finalización tiene que ser posterior al '
                .'vencimiento vigente. Si la fecha está mal digitada, corrígela en vez de renovar.',
        ];
    }

    public function prorroga(): EventoContractual
    {
        return EventoContractual::from((string) $this->validated('tipo'));
    }

    public function hasta(): Carbon
    {
        return Carbon::parse((string) $this->validated('fecha_fin'))->startOfDay();
    }

    /**
     * El contrato de la dirección, resuelto por el enrutador.
     *
     * Pasa por el global scope del trait, así que el contrato de otra empresa ya
     * respondió 404 antes de llegar aquí.
     */
    private function contrato(): Vinculacion
    {
        $contrato = $this->route('contrato');

        return $contrato instanceof Vinculacion ? $contrato : new Vinculacion;
    }
}
