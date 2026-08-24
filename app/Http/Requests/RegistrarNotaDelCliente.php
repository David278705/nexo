<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Las reglas de una nota de la bitácora (documento 4, 4.3).
 *
 * Un solo campo, porque la nota es un solo campo: la empresa llega en la
 * dirección, y el autor y la fecha los pone el servidor. Una fecha que se digita
 * es una fecha que se puede mover, y un autor que se digita es un autor que se
 * puede atribuir a otro.
 */
class RegistrarNotaDelCliente extends FormRequest
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
            'texto' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'texto.required' => 'Escriba la nota antes de guardarla.',
        ];
    }

    public function texto(): string
    {
        return trim((string) $this->input('texto'));
    }
}
