<?php

namespace App\Http\Controllers\Proveedor;

use App\Autenticacion\Invitaciones;
use App\Autorizacion\SeccionDelPanel;
use App\Catalogos\Ubicaciones;
use App\Configuracion\ParametrosDeLaEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\CrearEmpresa;
use App\Http\Requests\GuardarParametrosDeEmpresa;
use App\Http\Requests\RegistrarNotaDelCliente;
use App\Models\NotaCliente;
use App\Models\Tenant;
use App\Models\User;
use App\Presentacion\Formato;
use App\Proveedor\BitacoraDelCliente;
use App\Proveedor\CreacionDeEmpresa;
use App\Proveedor\EstadoDeCobro;
use App\Proveedor\EstadoDeEmpresa;
use App\Proveedor\FichaDeEmpresa;
use App\Proveedor\ListadoDeEmpresas;
use App\Proveedor\Salida\ExportacionDeLaEmpresa;
use App\Proveedor\SaludDeUso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

        return Inertia::render('Proveedor/Empresas', [
            ...$listado->paraLaPantalla(
                'empresas',
                fn (Tenant $empresa): array => ListadoDeEmpresas::fila($empresa),
            ),
            'opciones' => [
                'estados' => EstadoDeEmpresa::paraElegir(),
                'saludes' => SaludDeUso::paraElegir(),
                'planes' => ListadoDeEmpresas::planes(),
                'pagos' => EstadoDeCobro::paraElegir(),
                'tamanos' => ListadoDeEmpresas::TAMANOS_DE_PAGINA,
            ],
            'conteos' => ListadoDeEmpresas::conteos(),
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
     * Le manda a un usuario el enlace de su primer ingreso.
     *
     * Es la primera de las dos acciones de la pestaña «Usuarios», y desde
     * `PROD-22` es también **como se le entrega la cuenta al cliente**: crear la
     * empresa ya no envía la invitación, se envía desde aquí cuando su carga
     * inicial está lista. `emitir()` da por caducadas las invitaciones anteriores
     * del mismo usuario, así que enviar otra vez no reparte dos enlaces válidos.
     */
    public function reenviarInvitacion(Tenant $empresa, User $usuario, Invitaciones $invitaciones): RedirectResponse
    {
        $this->comprobarQueEsDeLaEmpresa($empresa, $usuario);

        $invitaciones->emitir($usuario);

        return redirect()
            ->route('proveedor.empresas.ficha', $empresa)
            ->with('estado', 'Se envió la invitación a '.$usuario->getAttribute('email')
                .'. Si había un enlace anterior, dejó de servir; el nuevo vence en '
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
     * Escribe una nota en la bitácora de ese cliente (documento 4, 4.3).
     *
     * Es interna del equipo del proveedor: no se le enseña al cliente por
     * ninguna vía, y por eso `NotaCliente` no se audita —el porqué completo está
     * en su migración—. La confirmación lo dice con esas palabras, para que
     * quien escribe sepa siempre a quién le está escribiendo.
     */
    public function agregarNota(RegistrarNotaDelCliente $peticion, Tenant $empresa): RedirectResponse
    {
        /** @var User $autor */
        $autor = $peticion->user();

        BitacoraDelCliente::de($empresa)->anotar($autor, $peticion->texto());

        return redirect()
            ->route('proveedor.empresas.ficha', $empresa)
            ->with('estado', BitacoraDelCliente::confirmacion($empresa));
    }

    /**
     * Quita una nota de la bitácora.
     *
     * Borrado lógico, como todo en el sistema: la fila se queda y deja de
     * aparecer. Y como en las dos acciones sobre usuarios, se comprueba que la
     * nota sea de la empresa de la dirección —lo hace `BitacoraDelCliente`—, o
     * bastaría cambiar un número para borrar la nota de otro cliente.
     */
    public function eliminarNota(Tenant $empresa, int $nota): RedirectResponse
    {
        $bitacora = BitacoraDelCliente::de($empresa);
        $encontrada = $bitacora->nota($nota);

        abort_unless($encontrada instanceof NotaCliente, 404);

        $encontrada->delete();

        return redirect()
            ->route('proveedor.empresas.ficha', $empresa)
            ->with('estado', BitacoraDelCliente::CONFIRMACION_DE_BORRADO);
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
    /**
     * Toda la información de la empresa en un ZIP, para entregársela cuando se
     * va (documento 6, A.4, paso 1). Deja la constancia de la entrega en la
     * empresa, que es desde donde cuenta el periodo de gracia.
     */
    public function exportacion(Tenant $empresa, ExportacionDeLaEmpresa $exportacion): BinaryFileResponse
    {
        abort_if($empresa->getAttribute('datos_eliminados_en') !== null, 404);

        $nombre = 'informacion-'.Str::slug((string) $empresa->getAttribute('nit')).'-'.now()->format('Y-m-d').'.zip';

        return response()->download($exportacion->generar($empresa), $nombre)->deleteFileAfterSend();
    }

    public function certificado(Tenant $empresa): StreamedResponse
    {
        abort_unless($empresa->tieneCertificadoDeCamara(), 404);

        return Storage::disk(CreacionDeEmpresa::discoDeCertificados())->download(
            (string) $empresa->getAttribute('certificado_camara_ruta'),
            (string) $empresa->getAttribute('certificado_camara_nombre'),
        );
    }
}
