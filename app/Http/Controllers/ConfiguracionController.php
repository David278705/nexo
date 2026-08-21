<?php

namespace App\Http\Controllers;

use App\Configuracion\ParametrosDeLaEmpresa;
use App\Http\Requests\GuardarParametros;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;

/**
 * La configuración de la empresa (documento 3, sección 5.7).
 *
 * De las cuatro pestañas de la pantalla, esta tarea construye «Parámetros». Las
 * otras tres —datos de la empresa, usuarios y permisos, y facturación— llegan en
 * su propia tarea y hasta entonces la pantalla lo dice en vez de fingir que
 * están.
 *
 * Aquí no se explica ningún parámetro y no se guarda ningún booleano: los textos
 * y la escritura son de `ParametrosDeLaEmpresa`. Este controlador traduce la
 * petición en props.
 */
class ConfiguracionController extends Controller
{
    public function parametros(): RespuestaInertia
    {
        return Inertia::render(
            'Configuracion/Parametros',
            ParametrosDeLaEmpresa::de($this->empresa())->paraLaPantalla(),
        );
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
