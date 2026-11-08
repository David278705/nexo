<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Marcar a un empleado con la leyenda «reclamo en trámite» (`PROD-14`, Ley 1581,
 * artículo 18, literal g).
 *
 * Solo el superadministrador: es quien representa a la empresa, que es la
 * responsable del tratamiento y la que decide el reclamo. El módulo de empleados
 * ya lo exige la ruta; aquí se exige además el rol.
 */
class MarcarReclamoEnTramite extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario instanceof User && $usuario->esSuperadministrador();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['motivo' => 'motivo del reclamo'];
    }
}
