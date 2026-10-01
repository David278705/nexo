<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Un pago recibido, con su factura (documento 4, capítulo 6).
 *
 * El monto se escribe y no se toma del plan: un cliente puede pagar dos meses
 * juntos, o un valor pactado distinto del de la tabla, y poner el precio del
 * plan por omisión haría que ese caso se registrara mal sin que nadie lo note.
 *
 * El número de factura es opcional porque la facturación electrónica ante la
 * DIAN es la decisión A.6 y todavía no está habilitada; cuando está, es único en
 * todo el sistema, igual que en la base de datos.
 */
class RegistrarPago extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario instanceof User && $usuario->esOwner();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Con dos decimales como mucho, como el salario: además de fijar la
            // precisión, rechaza «380.000», que para PHP sería 380 y no 380 mil.
            'monto' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999999'],
            'fecha' => ['required', 'date'],
            'numero_factura' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('pagos', 'numero_factura')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto.required' => 'Escriba cuánto entró.',
            'monto.min' => 'Un pago de cero no es un pago.',
            'fecha.required' => 'Escriba la fecha del pago.',
            'numero_factura.unique' => 'Ya hay un pago registrado con ese número de factura.',
        ];
    }

    /**
     * El monto como texto decimal, nunca como `float` (CLAUDE.md: el dinero va
     * en decimales de precisión fija). La columna es `numeric(14,2)`.
     */
    public function monto(): string
    {
        return (string) $this->input('monto');
    }

    public function numeroFactura(): ?string
    {
        $numero = trim((string) $this->input('numero_factura', ''));

        return $numero === '' ? null : $numero;
    }
}
