<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Lo que hace falta para generar un acceso de prueba (documento 4, capítulo
 * 10 y 12.1): a quién se le entrega y cuántos días dura.
 *
 * **`dias_de_vigencia` no tiene un valor por omisión.** El documento 6, D.12,
 * deja sin decidir la vigencia del usuario de demostración; lo que sí dice el
 * documento 4 es quién la decide cada vez —David, al generar el acceso—, así
 * que el campo es obligatorio y no llega con ningún número ya puesto.
 */
class GenerarAccesoDePrueba extends FormRequest
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
            'nombre_prospecto' => ['required', 'string', 'max:255'],
            'dias_de_vigencia' => ['required', 'integer', 'min:1', 'max:90'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre_prospecto.required' => 'Escriba a quién se le entrega el acceso, para poder identificarlo en la lista.',
            'dias_de_vigencia.required' => 'Diga cuántos días debe durar el acceso.',
            'dias_de_vigencia.min' => 'El acceso debe durar al menos un día.',
            'dias_de_vigencia.max' => 'Un acceso de prueba de más de 90 días ya no es una prueba: para eso está crear la empresa.',
        ];
    }

    public function nombreProspecto(): string
    {
        return (string) $this->input('nombre_prospecto');
    }

    public function diasDeVigencia(): int
    {
        return $this->integer('dias_de_vigencia');
    }
}
