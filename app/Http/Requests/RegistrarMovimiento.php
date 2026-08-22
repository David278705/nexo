<?php

namespace App\Http\Requests;

use App\Models\Vinculacion;
use App\Movimientos\TipoDeMovimiento;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Las reglas del registro de un aumento salarial o una promoción
 * (documento 1, 8.1).
 *
 * El acceso al módulo lo verifica el middleware `modulo:movimientos` de la
 * ruta. Lo que aquí se comprueba y no se puede dar por hecho:
 *
 * - **El empleado tiene que existir y estar activo en esta empresa.** El mismo
 *   flujo compartido de la sección 6.1 y la misma comprobación que usan
 *   `RegistrarIncapacidad`, `RegistrarVacacion` y `RegistrarLicencia`.
 * - **El nuevo salario es dinero**, con el mismo formato que el salario del
 *   registro del empleado: nunca coma flotante ni texto.
 * - **En una promoción el nuevo cargo es obligatorio.** En un aumento salarial
 *   no se pide: se repite el actual, y de eso se encarga `TipoDeMovimiento`.
 *
 * Lo que aquí **no** se comprueba, porque ninguna norma ni documento lo fija:
 * que el nuevo salario sea mayor que el actual, y que la promoción estrene un
 * cargo distinto del anterior. Un movimiento puede corregir una digitación o
 * reflejar un acuerdo que el sistema no tiene por qué juzgar.
 */
class RegistrarMovimiento extends FormRequest
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
            'tipo' => ['required', Rule::in(TipoDeMovimiento::valores())],
            'cargo_nuevo' => [
                'required_if:tipo,'.TipoDeMovimiento::Promocion->value,
                'nullable',
                'string',
                'max:255',
            ],
            'salario_nuevo' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'fecha_efectividad' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vinculacion_id' => 'empleado',
            'tipo' => 'tipo de movimiento',
            'cargo_nuevo' => 'nuevo cargo',
            'salario_nuevo' => 'nuevo salario',
            'fecha_efectividad' => 'fecha de efectividad',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cargo_nuevo.required_if' => 'Una promoción cambia de cargo: escribe el nuevo.',
        ];
    }

    public function vinculacion(): Vinculacion
    {
        return Vinculacion::query()->activas()->findOrFail($this->validated('vinculacion_id'));
    }

    public function tipo(): TipoDeMovimiento
    {
        return TipoDeMovimiento::from((string) $this->validated('tipo'));
    }

    public function cargoNuevo(): ?string
    {
        $cargo = $this->validated('cargo_nuevo');

        return is_string($cargo) ? $cargo : null;
    }

    public function salarioNuevo(): string
    {
        return (string) $this->validated('salario_nuevo');
    }

    public function fechaDeEfectividad(): Carbon
    {
        return Carbon::parse((string) $this->validated('fecha_efectividad'));
    }

    /**
     * El empleado del movimiento tiene que existir en esta empresa y seguir
     * activo. `Vinculacion::activas()` pasa por el trait `PerteneceATenant`,
     * así que el empleado de otra empresa nunca llega hasta aquí sin necesidad
     * de comprobarlo aparte.
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
