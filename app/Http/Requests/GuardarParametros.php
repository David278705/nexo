<?php

namespace App\Http\Requests;

use App\Autorizacion\Modulo;
use App\Configuracion\ParametrosDeLaEmpresa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Las reglas de «guardar los parámetros» (documento 1, capítulo 4).
 *
 * `authorize()` vuelve a preguntar por el módulo aunque la ruta ya lo exija con
 * el middleware. No es redundancia inútil: `Configuracion` es el único módulo
 * que no se puede asignar a nadie —solo lo tiene el superadministrador por su
 * rol— y esta es la única acción de la aplicación del cliente que escribe en la
 * tabla `tenants`. Una ruta que un día pierda su middleware por una edición
 * distraída seguiría respondiendo 403.
 *
 * Qué empresa se toca no se pregunta ni se recibe: es siempre la del usuario
 * autenticado. No hay ningún parámetro de la petición del que se pueda deducir
 * otra.
 */
class GuardarParametros extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('modulo', Modulo::Configuracion);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ParametrosDeLaEmpresa::reglas();
    }

    /**
     * Los tres valores, ya validados.
     *
     * @return array<string, string>
     */
    public function parametros(): array
    {
        /** @var array<string, string> $valores */
        $valores = $this->only(ParametrosDeLaEmpresa::CAMPOS);

        return $valores;
    }
}
