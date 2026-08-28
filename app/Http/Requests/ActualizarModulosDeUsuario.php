<?php

namespace App\Http\Requests;

use App\Autorizacion\Modulo;
use App\Configuracion\UsuariosDeLaEmpresa;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Las reglas de editar los módulos de un administrador (documento 3, 5.7).
 *
 * `authorize()` comprueba, además del módulo, que el usuario de la dirección
 * sea un administrador **de esta empresa**: `User` no lleva `PerteneceATenant`
 * —el porqué completo está en el propio modelo—, así que el enlace directo con
 * el identificador de otra empresa no lo frena ningún global scope. El
 * superadministrador tampoco se toca por aquí: tiene todos los módulos por su
 * rol y no por asignación (documento 1, capítulo 3).
 */
class ActualizarModulosDeUsuario extends FormRequest
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
        return UsuariosDeLaEmpresa::reglasParaModulos();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return UsuariosDeLaEmpresa::atributos();
    }

    private function esAdministradorDeLaEmpresa(): bool
    {
        $usuario = $this->route('usuario');

        return $usuario instanceof User
            && $usuario->getAttribute('tenant_id') === tenant_actual_id()
            && $usuario->esAdministrador();
    }
}
