<?php

namespace App\Http\Requests;

use App\Licencias\TipoDeLicencia;
use App\Models\Vinculacion;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Las reglas del registro de una licencia, ausencia o suspensión
 * (documento 1, 6.5).
 *
 * El acceso al módulo lo verifica el middleware `modulo:licencias` de la
 * ruta. Lo que aquí se comprueba y no se puede dar por hecho:
 *
 * - **El empleado tiene que existir y estar activo en esta empresa.** La
 *   misma regla absoluta de la sección 6.1, y la misma comprobación que usan
 *   `RegistrarIncapacidad` y `RegistrarVacacion`.
 * - **La fecha final no es anterior a la inicial.**
 */
class RegistrarLicencia extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vinculacion_id' => ['required', 'integer', $this->deUnEmpleadoActivo()],
            'tipo' => ['required', Rule::in(TipoDeLicencia::valores())],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vinculacion_id' => 'empleado',
            'tipo' => 'tipo de novedad',
            'fecha_inicio' => 'fecha inicial',
            'fecha_fin' => 'fecha final',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_fin.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }

    public function vinculacion(): Vinculacion
    {
        return Vinculacion::query()->activas()->findOrFail($this->validated('vinculacion_id'));
    }

    /**
     * El empleado que registra la novedad tiene que existir en esta empresa y
     * seguir activo. `Vinculacion::activas()` pasa por el trait
     * `PerteneceATenant`, así que el empleado de otra empresa nunca pasa por
     * aquí sin necesidad de comprobarlo aparte.
     */
    private function deUnEmpleadoActivo(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla): void {
            if (! Vinculacion::query()->activas()->whereKey($valor)->exists()) {
                $falla('El empleado no existe o no está activo.');
            }
        };
    }
}
