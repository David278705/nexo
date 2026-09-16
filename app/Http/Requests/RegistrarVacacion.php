<?php

namespace App\Http\Requests;

use App\Models\Vinculacion;
use App\Novedades\ComprobacionDeTraslape;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

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
 * - **No se pisa con otra novedad del empleado.** `HD-03`: dos periodos de
 *   vacaciones que se solapan, unas vacaciones sobre una incapacidad ya
 *   registrada, o vacaciones y suspensión al mismo tiempo. Lo decide
 *   `ComprobacionDeTraslape`, el único sitio que compara fechas para los tres
 *   módulos de novedades.
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
     * `HD-03`: ningún periodo de vacaciones se guarda si se pisa con otra
     * novedad del mismo empleado. Va como regla `after` y no como regla de
     * campo porque necesita las dos fechas ya validadas a la vez, y solo
     * corre si `vinculacion_id`, `fecha_inicio` y `fecha_fin` ya pasaron sus
     * propias reglas: comparar fechas que ni siquiera son fechas no dice nada.
     */
    protected function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            if ($validador->errors()->hasAny(['vinculacion_id', 'fecha_inicio', 'fecha_fin'])) {
                return;
            }

            $mensaje = ComprobacionDeTraslape::paraVacaciones(
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

    /**
     * Al registrar, el empleado es el que se está digitando. `EditarVacacion`
     * lo cambia por el de la fila que se está corrigiendo, porque el campo no
     * viaja en el formulario de edición.
     */
    protected function vinculacionIdParaTraslape(): int
    {
        return (int) $this->input('vinculacion_id');
    }

    /**
     * Nulo al registrar: no hay fila propia que excluir de la comparación.
     * `EditarVacacion` la excluye, para que un periodo no se pise consigo
     * mismo al guardar sin haber cambiado las fechas.
     */
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
