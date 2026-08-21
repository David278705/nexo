<?php

namespace App\Http\Requests;

use App\Proveedor\CreacionDeEmpresa;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Las reglas de «crear una empresa» (documento 4, 4.2).
 *
 * `authorize()` vuelve a preguntar por el rol aunque la ruta ya lo exija con el
 * middleware `proveedor`. Es la misma cautela de `GuardarParametros` y por un
 * motivo mayor: esta es la única acción de todo el sistema que crea una empresa
 * y su superadministrador. Una ruta que un día pierda su middleware por una
 * edición distraída seguiría respondiendo 403.
 */
class CrearEmpresa extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esOwner() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CreacionDeEmpresa::reglas();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return CreacionDeEmpresa::atributos();
    }
}
