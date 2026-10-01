<?php

namespace App\Http\Controllers;

use App\Legal\AceptacionDeLaEmpresa;
use App\Legal\DocumentoLegal;
use App\Legal\DocumentosLegales;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aceptar los documentos legales a nombre de la empresa, y leerlos.
 *
 * La aceptación la ve todo usuario de una empresa con algo pendiente, porque
 * `ExigirAceptacionLegal` no lo deja pasar a ninguna otra parte; pero solo el
 * superadministrador puede aceptar. Los demás ven quién tiene que hacerlo.
 */
class AceptacionLegalController extends Controller
{
    public function mostrar(Request $peticion): Response|RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $peticion->user();
        $empresa = tenant_actual();

        if (! $empresa instanceof Tenant) {
            return redirect()->to($usuario->paginaDeInicio());
        }

        $aceptacion = AceptacionDeLaEmpresa::de($empresa);
        $pendientes = $aceptacion->pendientes();

        // Una pantalla que pide aceptar lo que ya se aceptó es peor que ninguna.
        if ($pendientes === []) {
            return redirect()->to($usuario->paginaDeInicio());
        }

        return Inertia::render('Legal/Aceptacion', [
            'empresa' => (string) $empresa->getAttribute('nombre'),
            'puedeAceptar' => $usuario->esSuperadministrador(),
            'superadministrador' => $aceptacion->superadministrador()?->name,
            'documentos' => array_map(fn (DocumentoLegal $d) => [
                'clave' => $d->clave,
                'titulo' => $d->titulo,
                'version' => $d->version,
                'url' => $d->url(),
            ], $pendientes),
        ]);
    }

    public function aceptar(Request $peticion): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $peticion->user();
        $empresa = tenant_actual();

        abort_unless($empresa instanceof Tenant && $usuario->esSuperadministrador(), 403,
            'Solo el superadministrador puede aceptar los documentos a nombre de la empresa.');

        $peticion->validate(
            ['acepto' => ['accepted']],
            ['acepto.accepted' => 'Marca la casilla para aceptar a nombre de la empresa.'],
        );

        AceptacionDeLaEmpresa::de($empresa)->aceptar($usuario, $peticion->ip(), $peticion->userAgent());

        return redirect()->to($usuario->paginaDeInicio())
            ->with('estado', 'Gracias. La aceptación quedó registrada a nombre de '.$empresa->getAttribute('nombre').'.');
    }

    /**
     * Un documento, para leerlo. Es público: lo enlazan la pantalla de
     * aceptación y el sitio web, y cualquiera debe poder leer un contrato antes
     * de firmarlo.
     */
    public function documento(string $clave): Response
    {
        $documento = app(DocumentosLegales::class)->porClave($clave);

        abort_unless($documento instanceof DocumentoLegal, 404);

        return Inertia::render('Legal/Documento', [
            'titulo' => $documento->titulo,
            'version' => $documento->version,
            'esBorrador' => $documento->esBorrador,
            'html' => $documento->html(),
        ]);
    }
}
