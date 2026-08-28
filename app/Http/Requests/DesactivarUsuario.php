<?php

namespace App\Http\Requests;

use App\Autorizacion\Modulo;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Las reglas de desactivar un usuario (documento 3, 5.7): «un usuario se
 * desactiva, no se borra». No hay ningún dato que pedir, solo la comprobación
 * de que el usuario de la dirección es un administrador **de esta empresa** —
 * el mismo candado de `ActualizarModulosDeUsuario`, y por el mismo motivo—.
 *
 * El superadministrador no se puede desactivar desde aquí: es único por
 * empresa (documento 1, capítulo 3) y esta pantalla no ofrece forma de crear
 * otro, así que desactivarlo dejaría a la empresa sin nadie que entre.
 */
class DesactivarUsuario extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('modulo', Modulo::Configuracion) && $this->esAdministradorDeLaEmpresa();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    private function esAdministradorDeLaEmpresa(): bool
    {
        $usuario = $this->route('usuario');

        return $usuario instanceof User
            && $usuario->getAttribute('tenant_id') === tenant_actual_id()
            && $usuario->esAdministrador();
    }
}
