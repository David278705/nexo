<?php

namespace App\Http\Requests;

use App\Licencias\TipoDeLicencia;
use App\Models\Vinculacion;
use App\Novedades\ComprobacionDeTraslape;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
 * - **No se pisa con otra novedad del empleado.** `HD-03`: dos licencias que
 *   se solapan —cualquiera de los tres tipos, incluida la vacaciones y
 *   suspensión a la vez del documento 1, 6.2— o una licencia sobre una
 *   incapacidad ya registrada. Lo decide `ComprobacionDeTraslape::paraLicencia()`.
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
     * `HD-03`: la misma comprobación de traslape de `RegistrarVacacion`, con
     * la regla propia de las licencias.
     */
    protected function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            if ($validador->errors()->hasAny(['vinculacion_id', 'fecha_inicio', 'fecha_fin'])) {
                return;
            }

            $mensaje = ComprobacionDeTraslape::paraLicencia(
                $this->vinculacionIdParaTraslape(),
                Carbon::parse((string) $this->input('fecha_inicio')),
                Carbon::parse((string) $this->input('fecha_fin')),
                $this->idAExcluirDelTraslape(),
            );

            if ($mensaje !== null) {
                $validador->errors()->add('fecha_inicio', $mensaje);
            }
        });
    }

    protected function vinculacionIdParaTraslape(): int
    {
        return (int) $this->input('vinculacion_id');
    }

    protected function idAExcluirDelTraslape(): ?int
    {
        return null;
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
