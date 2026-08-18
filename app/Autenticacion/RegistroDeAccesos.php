<?php

namespace App\Autenticacion;

use App\Models\Acceso;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Escribe el registro de accesos. Es el único sitio donde se escribe.
 *
 * Se llama desde el controlador de sesión en los tres desenlaces posibles, para
 * que ninguno se pueda olvidar por separado: entrar, fallar y llegar bloqueado.
 */
class RegistroDeAccesos
{
    public function exitoso(Request $peticion, User $usuario): Acceso
    {
        return $this->registrar($peticion, ResultadoDeAcceso::Exitoso, $usuario->email, $usuario);
    }

    public function fallido(Request $peticion, string $correo, ?User $usuario = null): Acceso
    {
        return $this->registrar($peticion, ResultadoDeAcceso::Fallido, $correo, $usuario);
    }

    public function bloqueado(Request $peticion, string $correo, ?User $usuario = null): Acceso
    {
        return $this->registrar($peticion, ResultadoDeAcceso::Bloqueado, $correo, $usuario);
    }

    private function registrar(
        Request $peticion,
        ResultadoDeAcceso $resultado,
        string $correo,
        ?User $usuario,
    ): Acceso {
        return Acceso::create([
            'user_id' => $usuario?->getKey(),
            'tenant_id' => $usuario?->tenant_id,
            'correo' => $correo,
            'resultado' => $resultado->value,
            'ip' => $peticion->ip(),
            // El navegador entero no cabe en la columna y tampoco hace falta.
            'agente' => mb_substr((string) $peticion->userAgent(), 0, 255),
        ]);
    }
}
