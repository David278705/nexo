<?php

namespace App\Http\Controllers\Proveedor;

use App\Autenticacion\Invitaciones;
use App\Autorizacion\SeccionDelPanel;
use App\Catalogos\Ubicaciones;
use App\Configuracion\ParametrosDeLaEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\CrearEmpresa;
use App\Http\Requests\GuardarParametrosDeEmpresa;
use App\Models\Tenant;
use App\Models\User;
use App\Presentacion\Formato;
use App\Proveedor\CreacionDeEmpresa;
use App\Proveedor\EstadoDeEmpresa;
use App\Proveedor\FichaDeEmpresa;
use App\Proveedor\ListadoDeEmpresas;
use App\Proveedor\SaludDeUso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las empresas cliente, desde el panel del proveedor (documento 4, capítulo 4).
 *
 * Las tres pantallas del capítulo: el listado con sus indicadores de salud
 * (4.1), crear (4.2) y la ficha con sus pestañas (4.3).
 *
 * Aquí no se valida ni se escribe nada: la validación es `CrearEmpresa` y
 * `GuardarParametrosDeEmpresa`, la escritura de una empresa nueva —empresa,
 * cuenta e invitación en una sola transacción— es `CreacionDeEmpresa`, y lo que
 * cada pantalla enseña lo arman `ListadoDeEmpresas` y `FichaDeEmpresa`. Este
 * controlador traduce la petición en props.
 */
class EmpresaController extends Controller
{
    /**
     * El listado de clientes (documento 4, 4.1).
     *
     * Los filtros, el orden y la paginación se resuelven en el servidor, igual
     * que en los listados del entorno del cliente: la dirección lleva lo que se
     * está viendo y por eso se puede copiar, compartir y volver a ella desde la
     * ficha.
     */
    public function index(Request $peticion): RespuestaInertia
    {
        $listado = ListadoDeEmpresas::desde($peticion);
        $pagina = $listado->pagina();

        return Inertia::render('Proveedor/Empresas', [
            'empresas' => [
                'filas' => array_map(
                    fn (Tenant $empresa) => ListadoDeEmpresas::fila($empresa),
                    $pagina->items(),
                ),
                'total' => $pagina->total(),
                'pagina' => $pagina->currentPage(),
                'paginas' => $pagina->lastPage(),
                'desde' => $pagina->firstItem(),
                'hasta' => $pagina->lastItem(),
            ],
            'filtros' => $listado->paraLaInterfaz(),
            'aplicados' => $listado->aplicados(),
            'tieneFiltros' => $listado->tieneFiltros(),
            'opciones' => [
                'estados' => EstadoDeEmpresa::paraElegir(),
                'saludes' => SaludDeUso::paraElegir(),
                'planes' => ListadoDeEmpresas::planes(),
                'tamanos' => ListadoDeEmpresas::TAMANOS_DE_PAGINA,
            ],
            'conteos' => ListadoDeEmpresas::conteos(),
            'pagoPendiente' => ListadoDeEmpresas::PAGO_PENDIENTE,
            'accion' => ['texto' => '+ Crear empresa', 'url' => '/'.SeccionDelPanel::PREFIJO.'/empresas/nueva'],
        ]);
    }

    /**
     * La ficha de una empresa con sus siete pestañas (documento 4, 4.3).
     */
    public function ficha(Tenant $empresa): RespuestaInertia
    {
        return Inertia::render('Proveedor/FichaDeEmpresa', [
            ...FichaDeEmpresa::de($empresa)->paraLaPantalla(),
            'volver' => SeccionDelPanel::Empresas->url(),
        ]);
    }

    /**
     * Guarda los parámetros de esa empresa desde la ficha.
     *
     * La constancia del cambio que pide el documento no se escribe aquí: el
     * observador de auditoría la deja sola, con el `owner` como autor y **con la
     * empresa tocada como dueña de la línea**, que es lo que hace que el cliente
     * pueda ver en su propia auditoría que le cambiaron un parámetro.
     */
    public function guardarParametros(GuardarParametrosDeEmpresa $peticion, Tenant $empresa): RedirectResponse
    {
        ParametrosDeLaEmpresa::de($empresa)->guardar($peticion->parametros());

        return redirect()
            ->route('proveedor.empresas.ficha', $empresa)
            ->with('estado', FichaDeEmpresa::confirmacionDeParametros($empresa));
    }

    /**
     * El asistente de tres pasos.
     *
     * Los municipios viajan enteros con la pantalla, anidados en su
     * departamento, igual que en el registro de empleado: el encadenamiento es
     * entonces un filtro en el navegador y no una petición por cada
     * departamento que se prueba.
     */
    public function nueva(): RespuestaInertia
    {
        return Inertia::render('Proveedor/CrearEmpresa', [
            'pasos' => CreacionDeEmpresa::PASOS,
            'parametros' => ParametrosDeLaEmpresa::opciones(),
            'ubicaciones' => Ubicaciones::paraFormulario(),
            'certificado' => [
                'formatos' => CreacionDeEmpresa::FORMATOS_DEL_CERTIFICADO,
                'tamanoMaximoEnKb' => CreacionDeEmpresa::TAMANO_MAXIMO_EN_KB,
            ],
            'volver' => SeccionDelPanel::Empresas->url(),
        ]);
    }

    public function crear(CrearEmpresa $peticion, CreacionDeEmpresa $creacion): RedirectResponse
    {
        $empresa = $creacion->ejecutar($peticion->validated());

        /*
         * Se va al resumen y no al listado: el documento pide que al terminar se
         * muestre lo que quedó configurado para leérselo al cliente en voz alta
         * antes de cerrar la reunión. Un mensaje de confirmación que se va al
         * cambiar de pantalla no sirve para eso.
         */
        return redirect()
            ->route('proveedor.empresas.resumen', $empresa)
            ->with('estado', CreacionDeEmpresa::confirmacion($empresa));
    }

    /**
     * El resumen copiable del final de la reunión (documento 4, 4.2).
     *
     * Es una dirección propia y no una pantalla de un solo uso: se puede volver
     * a abrir, que es lo que hace falta cuando el cliente pide «mándame eso por
     * escrito» dos días después.
     */
    public function resumen(Tenant $empresa): RespuestaInertia
    {
        return Inertia::render('Proveedor/ResumenDeEmpresa', [
            'empresa' => ['nombre' => $empresa->getAttribute('nombre')],
            'titulo' => CreacionDeEmpresa::TITULO_DEL_RESUMEN,
            'renglones' => CreacionDeEmpresa::resumen($empresa),
            'texto' => CreacionDeEmpresa::textoParaCopiar($empresa),
            'certificado' => $empresa->tieneCertificadoDeCamara()
                ? ['url' => "/proveedor/empresas/{$empresa->getKey()}/certificado"]
                : null,
            'volver' => SeccionDelPanel::Empresas->url(),
        ]);
    }

    /**
     * Vuelve a mandarle a un usuario el enlace de su primer ingreso.
     *
     * Es la primera de las dos acciones de la pestaña «Usuarios». `emitir()` da
     * por caducadas las invitaciones anteriores del mismo usuario, así que
     * reenviar no reparte dos enlaces válidos: el viejo deja de servir en ese
     * momento.
     */
    public function reenviarInvitacion(Tenant $empresa, User $usuario, Invitaciones $invitaciones): RedirectResponse
    {
        $this->comprobarQueEsDeLaEmpresa($empresa, $usuario);

        $invitaciones->emitir($usuario);

        return redirect()
            ->route('proveedor.empresas.ficha', $empresa)
            ->with('estado', 'Se reenvió la invitación a '.$usuario->getAttribute('email')
                .'. El enlace anterior dejó de servir y el nuevo vence en '
                .Formato::dias(Invitaciones::DIAS_DE_VIGENCIA).'.');
    }

    /**
     * Le manda al usuario el enlace para que ponga una contraseña nueva.
     *
     * **No hay ninguna forma de ver la contraseña de nadie, y esta no lo es**:
     * el proveedor no llega a conocerla en ningún momento; el enlace va al
     * buzón del usuario y solo él lo abre. Es lo que promete la ficha de la
     * empresa en el documento 4, 4.3.
     */
    public function restablecerContrasena(Tenant $empresa, User $usuario): RedirectResponse
    {
        $this->comprobarQueEsDeLaEmpresa($empresa, $usuario);

        Password::sendResetLink(['email' => $usuario->getAttribute('email')]);

        return redirect()
            ->route('proveedor.empresas.ficha', $empresa)
            ->with('estado', 'Se envió a '.$usuario->getAttribute('email').' el enlace para definir '
                .'una contraseña nueva. Nadie más puede verla ni escribirla por él.');
    }

    /**
     * Que el usuario de la dirección sea de la empresa de la dirección.
     *
     * Las dos acciones reciben los dos identificadores por separado, así que sin
     * esta comprobación bastaría con cambiar un número a mano para reenviarle
     * una invitación a alguien de otra empresa desde la ficha de esta. Responde
     * 404 y no 403 porque, para esta ficha, ese usuario sencillamente no existe.
     */
    private function comprobarQueEsDeLaEmpresa(Tenant $empresa, User $usuario): void
    {
        abort_unless($usuario->getAttribute('tenant_id') === $empresa->getKey(), 404);
    }

    /**
     * Devuelve el certificado adjunto.
     *
     * El archivo vive en el disco privado, así que no tiene dirección propia:
     * esta ruta es la única forma de llegar a él y está dentro del panel, con su
     * middleware y su segundo factor. Un documento societario de un cliente no
     * puede quedar en `public` a la espera de que nadie adivine el nombre.
     */
    public function certificado(Tenant $empresa): StreamedResponse
    {
        abort_unless($empresa->tieneCertificadoDeCamara(), 404);

        return Storage::disk(CreacionDeEmpresa::DISCO_DE_CERTIFICADOS)->download(
            (string) $empresa->getAttribute('certificado_camara_ruta'),
            (string) $empresa->getAttribute('certificado_camara_nombre'),
        );
    }
}
