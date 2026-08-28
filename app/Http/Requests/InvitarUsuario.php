<?php

namespace App\Http\Requests;

use App\Autorizacion\Modulo;
use App\Configuracion\UsuariosDeLaEmpresa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Las reglas de invitar un usuario administrador (documento 3, 5.7): «se pide
 * nombre y correo, se marcan los módulos y se envía la invitación».
 *
 * `authorize()` repite la pregunta del módulo por el mismo motivo que
 * `GuardarParametros`: es la única acción de esta pantalla que crea una
 * cuenta nueva, y una ruta que un día pierda su middleware por una edición
 * distraída seguiría respondiendo 403.
 */
class InvitarUsuario extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('modulo', Modulo::Configuracion);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return UsuariosDeLaEmpresa::reglasParaCrear();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return UsuariosDeLaEmpresa::atributos();
    }
}
