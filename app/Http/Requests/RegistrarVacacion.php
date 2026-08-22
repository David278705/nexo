<?php

namespace App\Http\Requests;

use App\Models\Vinculacion;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Las reglas del registro de un periodo de vacaciones (documento 1, 6.4).
 *
 * El acceso al módulo lo verifica el middleware `modulo:vacaciones` de la
 * ruta. Lo que aquí se comprueba y no se puede dar por hecho:
 *
 * - **El empleado tiene que existir y estar activo en esta empresa.** La
 *   misma regla absoluta de la sección 6.1, y la misma comprobación que usa
 *   `RegistrarIncapacidad`.
 * - **El día final no es anterior al inicial.**
 *
 * La advertencia de saldo insuficiente (F2-06) no bloquea el registro y no
 * es parte de esta validación: el documento 1, 6.4 lo dice expresamente.
 */
class RegistrarVacacion extends FormRequest
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
            'fecha_inicio' => 'día inicial de vacaciones',
            'fecha_fin' => 'día final de vacaciones',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_fin.after_or_equal' => 'El día final no puede ser anterior al inicial.',
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
