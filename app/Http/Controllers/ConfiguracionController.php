<?php

namespace App\Http\Controllers;

use App\Configuracion\FacturacionDeLaEmpresa;
use App\Configuracion\ParametrosDeLaEmpresa;
use App\Configuracion\UsuariosDeLaEmpresa;
use App\Http\Requests\ActualizarModulosDeUsuario;
use App\Http\Requests\DesactivarUsuario;
use App\Http\Requests\GuardarParametros;
use App\Http\Requests\InvitarUsuario;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;

/**
 * La configuración de la empresa (documento 3, sección 5.7).
 *
 * Las cuatro pestañas de la pantalla ya tienen contenido: «Parámetros» y
 * «Datos de la empresa» son de F1-12, «Usuarios y permisos» de REV-10 y
 * «Facturación» de REV-11.
 *
 * Aquí no se explica ningún parámetro, no se guarda ningún booleano, no se
 * decide ninguna regla sobre usuarios y no se calcula ningún cobro: los
 * textos y la escritura son de `ParametrosDeLaEmpresa`, `UsuariosDeLaEmpresa`
 * y `FacturacionDeLaEmpresa`. Este controlador traduce la petición en props y
 * en llamadas a esas tres clases.
 */
class ConfiguracionController extends Controller
{
    public function parametros(): RespuestaInertia
    {
        $empresa = $this->empresa();

        return Inertia::render('Configuracion/Parametros', [
            ...ParametrosDeLaEmpresa::de($empresa)->paraLaPantalla(),
            ...UsuariosDeLaEmpresa::de($empresa)->paraLaPantalla(),
            ...FacturacionDeLaEmpresa::de($empresa)->paraLaPantalla(),
        ]);
    }

    /**
     * Guarda los tres parámetros de cálculo.
     *
     * No hay nada que decidir aquí: quién puede guardar y qué valores se
     * admiten los comprueba `GuardarParametros`, y el cambio lo escribe
     * `ParametrosDeLaEmpresa::guardar()`, que deja **una** línea de auditoría
     * con lo que cambió porque es una sola edición de la empresa.
     */
    public function guardarParametros(GuardarParametros $peticion): RedirectResponse
    {
        ParametrosDeLaEmpresa::de($this->empresa())->guardar($peticion->parametros());

        return redirect()
            ->route('configuracion')
            ->with('estado', 'Se guardaron los parámetros. Los cálculos y los correos usan los '
                .'valores nuevos desde ahora.');
    }

    /**
     * Invita a un administrador nuevo, con los módulos ya marcados.
     */
    public function crearUsuario(InvitarUsuario $peticion): RedirectResponse
    {
        $usuario = UsuariosDeLaEmpresa::de($this->empresa())->crear($peticion->validated());

        return redirect()
            ->route('configuracion')
            ->with('estado', UsuariosDeLaEmpresa::confirmacionDeInvitacion($usuario));
    }

    /**
     * Reemplaza los módulos asignados a un administrador existente.
     */
    public function actualizarModulosDeUsuario(ActualizarModulosDeUsuario $peticion, User $usuario): RedirectResponse
    {
        /** @var list<string> $modulos */
        $modulos = $peticion->validated('modulos', []);

        UsuariosDeLaEmpresa::de($this->empresa())->actualizarModulos($usuario, $modulos);

        return redirect()
            ->route('configuracion')
            ->with('estado', UsuariosDeLaEmpresa::confirmacionDeModulos($usuario));
    }

    /**
     * Desactiva a un administrador: borrado lógico, no borra la fila.
     */
    public function desactivarUsuario(DesactivarUsuario $peticion, User $usuario): RedirectResponse
    {
        $confirmacion = UsuariosDeLaEmpresa::confirmacionDeDesactivar($usuario);

        UsuariosDeLaEmpresa::de($this->empresa())->desactivar($usuario);

        return redirect()
            ->route('configuracion')
            ->with('estado', $confirmacion);
    }

    /**
     * La empresa del usuario autenticado, la única que esta pantalla toca.
     *
     * Falta solo si no hay contexto de empresa, y entonces no hay nada que
     * configurar: el `owner` no entra por aquí —su sitio es el panel del
     * proveedor— y un usuario de empresa sin empresa ya recibió 403 de la
     * política de módulos.
     */
    private function empresa(): Tenant
    {
        return tenant_actual() ?? abort(403);
    }
}
