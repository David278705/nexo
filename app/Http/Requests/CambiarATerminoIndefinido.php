<?php

namespace App\Http\Requests;

use App\Contratacion\CambioAIndefinido;
use App\Models\Vinculacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Las reglas de la acción «Cambiar a término indefinido» (documento 1, 5.5).
 *
 * La regla de existencia de la acción está en `authorize()`: **se convierten el
 * término fijo y la obra o labor** (`PROD-16`). En un indefinido el cambio no
 * significa nada, así que enviar la petición a mano responde 403 y no un error
 * de validación de un campo que no existe.
 *
 * El cambio inverso tampoco existe, y no hace falta prohibirlo: no hay ninguna
 * ruta que lo haga.
 *
 * La única regla sobre datos es la fecha del cambio (HD-11): no puede ser
 * anterior al inicio del periodo vigente ni posterior a hoy. Los dos límites
 * salen de `CambioAIndefinido::limiteInferior()` y `::limiteSuperior()`, los
 * mismos que ya usa la pantalla para proponer la fecha por defecto: el rango
 * que se valida aquí es literalmente el que el modal enseña.
 */
class CambiarATerminoIndefinido extends FormRequest
{
    public function authorize(): bool
    {
        return $this->cambio()->disponible();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => [
                'required',
                'date',
                'after_or_equal:'.$this->cambio()->limiteInferior()->toDateString(),
                'before_or_equal:'.$this->cambio()->limiteSuperior()->toDateString(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['fecha' => 'fecha del cambio'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.after_or_equal' => 'La fecha del cambio no puede ser anterior al inicio del '
                .'periodo vigente ('.$this->cambio()->limiteInferior()->format('d/m/Y').').',
            'fecha.before_or_equal' => 'La fecha del cambio no puede ser posterior a hoy.',
        ];
    }

    public function fecha(): Carbon
    {
        return Carbon::parse((string) $this->validated('fecha'))->startOfDay();
    }

    private function cambio(): CambioAIndefinido
    {
        return CambioAIndefinido::de($this->contrato());
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
