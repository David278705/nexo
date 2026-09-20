<?php

namespace App\Http\Requests;

use App\Models\Vinculacion;
use App\Terminaciones\ContratoSinAclarar;
use App\Terminaciones\TipoDeSalida;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Las reglas del registro de una terminación (documento 1, capítulo 7).
 *
 * El acceso al módulo lo verifica el middleware `modulo:terminaciones` de la
 * ruta. Lo que aquí se comprueba y no se puede dar por hecho:
 *
 * - **El empleado tiene que existir y estar activo en esta empresa.** La misma
 *   comprobación de los otros cuatro módulos del flujo compartido. Aquí hace
 *   además de guardia contra la doble salida: quien ya tiene terminación
 *   registrada está inactivo, así que no puede terminar dos veces.
 * - **El motivo tiene que ser uno de los de ese tipo de salida** (7.3). Es la
 *   lista dependiente comprobada en el servidor: que el navegador recargue las
 *   opciones al cambiar el tipo es comodidad, no control.
 * - **En expiración del plazo y obra terminada el motivo no se pide.** Lo
 *   autocompleta `TipoDeSalida::motivoResultante()` y se ignora el que llegue.
 * - **Un contrato a término fijo no puede quedar en el aire** (documento 6,
 *   D.7). Si la fecha de salida cae después del último vencimiento registrado,
 *   la salida se bloquea diciendo el vacío exacto: ver `ContratoSinAclarar`.
 *
 * Lo que aquí **no** se comprueba, porque ningún documento lo fija: que la
 * fecha de salida no sea futura y que no sea anterior a la de ingreso. Una
 * salida se registra a veces días después y a veces con antelación —una
 * renuncia avisada—, y no hay norma que fije un rango.
 */
class RegistrarTerminacion extends FormRequest
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
            'tipo_salida' => ['required', Rule::in(TipoDeSalida::valores())],
            'motivo' => ['nullable', 'string'],
            'fecha_salida' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vinculacion_id' => 'empleado',
            'tipo_salida' => 'tipo de salida',
            'motivo' => 'motivo de la baja',
            'fecha_salida' => 'fecha de salida',
        ];
    }

    public function vinculacion(): Vinculacion
    {
        return Vinculacion::query()->activas()->findOrFail($this->validated('vinculacion_id'));
    }

    public function tipoDeSalida(): TipoDeSalida
    {
        return TipoDeSalida::from((string) $this->validated('tipo_salida'));
    }

    public function motivo(): ?string
    {
        $motivo = $this->validated('motivo');

        return is_string($motivo) ? $motivo : null;
    }

    public function fechaDeSalida(): Carbon
    {
        return Carbon::parse((string) $this->validated('fecha_salida'));
    }

    public function observaciones(): ?string
    {
        $observaciones = $this->validated('observaciones');

        return is_string($observaciones) && trim($observaciones) !== '' ? trim($observaciones) : null;
    }

    /**
     * El empleado de la terminación tiene que existir en esta empresa y seguir
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

    /**
     * El motivo tiene que pertenecer al tipo de salida elegido, y es
     * obligatorio salvo cuando el tipo lo autocompleta.
     *
     * Va aquí y no como regla del campo por una razón concreta: el motivo llega
     * vacío en los dos casos que hay que distinguir —el que hay que exigir y el
     * que se autocompleta—, y una regla `nullable` deja de mirar el campo en
     * cuanto lo ve nulo, que es justo cuando hace falta mirarlo.
     */
    protected function withValidator(Validator $validador): void
    {
        $validador->after($this->comprobarElContrato(...));
        $validador->after(function (Validator $validador): void {
            $tipo = TipoDeSalida::tryFrom((string) $this->input('tipo_salida'));

            if (! $tipo instanceof TipoDeSalida || $tipo->motivoAutomatico() !== null) {
                return;
            }

            $motivo = $this->input('motivo');

            if (! is_string($motivo) || trim($motivo) === '') {
                $validador->errors()->add('motivo', 'Elige el motivo de la baja.');

                return;
            }

            if (! in_array($motivo, $tipo->motivos(), true)) {
                $validador->errors()->add('motivo', 'Ese motivo no corresponde al tipo de salida elegido.');
            }
        });
    }

    /**
     * La vinculación a la que pertenece esta salida: la del empleado elegido al
     * registrar. La corrección la sobrescribe, porque ahí el empleado no se
     * digita: es el de la salida que se está corrigiendo.
     */
    protected function vinculacionDeLaSalida(): ?Vinculacion
    {
        return Vinculacion::query()->find($this->input('vinculacion_id'));
    }

    /**
     * Bloquea la salida que dejaría el contrato a término fijo sin aclarar
     * (documento 6, D.7). El error va en la fecha de salida, que es el dato que
     * hay que cambiar, o que obliga a aclarar primero el contrato.
     *
     * Solo se mira si el empleado y la fecha ya pasaron sus propias reglas: un
     * segundo error sobre una fecha que ni siquiera es una fecha no ayuda.
     */
    private function comprobarElContrato(Validator $validador): void
    {
        if ($validador->errors()->hasAny(['fecha_salida', 'vinculacion_id'])) {
            return;
        }

        $vinculacion = $this->vinculacionDeLaSalida();

        if (! $vinculacion instanceof Vinculacion) {
            return;
        }

        $vacio = ContratoSinAclarar::para($vinculacion, $this->fechaDeSalida());

        if ($vacio instanceof ContratoSinAclarar) {
            $validador->errors()->add('fecha_salida', $vacio->mensaje());
        }
    }
}
