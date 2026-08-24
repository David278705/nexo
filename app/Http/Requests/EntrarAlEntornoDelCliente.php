<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Las reglas de «entrar al entorno del cliente» (documento 4, 4.4).
 *
 * El motivo es obligatorio y con mínimo, igual que el de una suspensión: lo va a
 * leer el cliente en su propia auditoría, y «revisión» no le dice nada dentro de
 * seis meses. El prototipo lo enuncia con esas palabras en la ayuda del campo.
 *
 * `authorize()` vuelve a preguntar por el rol aunque la ruta ya lo exija con el
 * middleware `proveedor`, por lo mismo que en `GuardarParametrosDeEmpresa`: es
 * la acción con más consecuencias de todo el panel y una ruta que un día pierda
 * su middleware por una edición distraída seguiría respondiendo 403.
 */
class EntrarAlEntornoDelCliente extends FormRequest
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
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
            'escritura' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Escriba para qué va a entrar. Queda registrado y el cliente lo ve.',
            'motivo.min' => 'Escriba un motivo que se entienda dentro de seis meses.',
        ];
    }

    public function motivo(): string
    {
        return trim((string) $this->input('motivo'));
    }

    /**
     * Si se pidió además poder escribir.
     *
     * Es una decisión expresa —hay que marcarla— y por eso el valor por omisión
     * es «no». La casilla sin marcar y la casilla ausente significan lo mismo:
     * entrar a mirar.
     */
    public function conEscritura(): bool
    {
        return $this->boolean('escritura');
    }
}
