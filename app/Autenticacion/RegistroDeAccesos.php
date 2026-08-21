<?php

namespace App\Autenticacion;

use App\Models\Acceso;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        $acceso = $this->registrar($peticion, ResultadoDeAcceso::Exitoso, $usuario->email, $usuario);

        $this->anotarElIngresoDeLaEmpresa($usuario);

        return $acceso;
    }

    public function fallido(Request $peticion, string $correo, ?User $usuario = null): Acceso
    {
        return $this->registrar($peticion, ResultadoDeAcceso::Fallido, $correo, $usuario);
    }

    public function bloqueado(Request $peticion, string $correo, ?User $usuario = null): Acceso
    {
        return $this->registrar($peticion, ResultadoDeAcceso::Bloqueado, $correo, $usuario);
    }

    /**
     * Deja en la empresa la fecha del último ingreso de cualquiera de los suyos.
     *
     * Es la columna `ultimo_acceso_en` de `tenants`, de la que salen el
     * indicador de salud de uso y la columna «Último ingreso» del listado de
     * empresas del panel (documento 4, 4.1). La migración la creó para que el
     * proceso diario la refrescara; se escribe aquí porque **este es el momento
     * en que el hecho ocurre**, y así el panel nunca enseña un dato de ayer.
     *
     * Va con `update()` del constructor de consultas y no con `save()` a
     * propósito: escribir la hora de un inicio de sesión no es una edición de la
     * empresa que merezca su línea de auditoría —eso ya lo cuenta `accesos`— y
     * una línea por cada entrada de cada usuario ahogaría la tabla.
     *
     * El `owner` no tiene empresa y no anota nada: el panel del proveedor no es
     * uso del producto por parte de ningún cliente.
     */
    private function anotarElIngresoDeLaEmpresa(User $usuario): void
    {
        $empresa = $usuario->tenant_id;

        if ($empresa === null) {
            return;
        }

        Tenant::query()->whereKey($empresa)->update(['ultimo_acceso_en' => Carbon::now()]);
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
