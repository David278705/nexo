<?php

namespace App\Http\Requests;

use App\Configuracion\ParametrosDeLaEmpresa;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Las reglas de «editar los parámetros de una empresa desde el panel»
 * (documento 4, 4.3: «los cuatro parámetros, editables por el owner con
 * constancia del cambio»).
 *
 * Es la gemela de `GuardarParametros`, la del entorno del cliente, y comparte
 * con ella las reglas —las de `ParametrosDeLaEmpresa`— pero no la autorización,
 * que es justo lo contrario de la otra:
 *
 * - En el entorno del cliente, quien guarda es el superadministrador y la
 *   empresa **no se recibe**: es siempre la suya.
 * - Aquí, quien guarda es el `owner` y la empresa **sí llega en la dirección**,
 *   porque atravesar empresas es exactamente lo que el panel hace.
 *
 * `authorize()` vuelve a preguntar por el rol aunque la ruta ya lo exija con el
 * middleware `proveedor`. Esta es la única acción del panel que escribe en la
 * configuración de un cliente, y una ruta que un día pierda su middleware por
 * una edición distraída seguiría respondiendo 403.
 */
class GuardarParametrosDeEmpresa extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario instanceof User && $usuario->esOwner();
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
