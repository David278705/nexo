<?php

namespace App\Http\Requests;

use App\Incapacidades\OrigenIncapacidad;
use App\Models\Vinculacion;
use App\Novedades\ComprobacionDeTraslape;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Las reglas del registro de una incapacidad (documento 1, 6.3).
 *
 * El acceso al módulo lo verifica el middleware `modulo:incapacidades` de la
 * ruta. Lo que aquí se comprueba y no se puede dar por hecho:
 *
 * - **El empleado tiene que existir y estar activo en esta empresa.** Es la
 *   regla absoluta de la sección 6.1: ninguna novedad se registra sobre un
 *   documento que no corresponda a un empleado activo. El flujo compartido de
 *   novedades ya solo ofrece empleados así, pero lo que ofrece la pantalla no
 *   es una validación: quien envía el formulario a mano se encuentra con esta
 *   regla igual.
 * - **La fecha de terminación no es anterior a la de inicio.** Una incapacidad
 *   de un solo día tiene las dos fechas iguales, así que se admite `>=`.
 * - **No se pisa con otra incapacidad ya registrada.** `HD-03`: la
 *   comprobación es `ComprobacionDeTraslape::paraIncapacidad()`, que solo mira
 *   otras incapacidades —el caso contrario, una incapacidad que cae sobre
 *   vacaciones ya registradas, no se bloquea aquí, es `HD-04`—.
 */
class RegistrarIncapacidad extends FormRequest
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
            'codigo_diagnostico' => ['required', 'string', 'max:20'],
            'es_prorroga' => ['required', Rule::in(['si', 'no'])],
            'eps_id' => ['required', Rule::exists('eps', 'id')->where('activo', true)->whereNull('deleted_at')],
            'origen' => ['required', Rule::in(OrigenIncapacidad::valores())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vinculacion_id' => 'empleado',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de terminación',
            'codigo_diagnostico' => 'código de diagnóstico',
            'es_prorroga' => 'prórroga',
            'eps_id' => 'EPS',
            'origen' => 'origen',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_fin.after_or_equal' => 'La fecha de terminación no puede ser anterior a la de inicio.',
        ];
    }

    public function vinculacion(): Vinculacion
    {
        return Vinculacion::query()->activas()->findOrFail($this->validated('vinculacion_id'));
    }

    public function esProrroga(): bool
    {
        return $this->validated('es_prorroga') === 'si';
    }

    /**
     * `HD-03`: la misma comprobación de traslape de `RegistrarVacacion`, con
     * la regla propia de las incapacidades —solo mira otras incapacidades—.
     */
    protected function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            if ($validador->errors()->hasAny(['vinculacion_id', 'fecha_inicio', 'fecha_fin'])) {
                return;
            }

            $mensaje = ComprobacionDeTraslape::paraIncapacidad(
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
