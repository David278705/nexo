<?php

namespace App\Http\Controllers;

use App\Legal\DocumentoLegal;
use App\Legal\DocumentosLegales;
use App\Models\Plan;
use App\Presentacion\Formato;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La página pública de Nexo (`PROD-27`): qué hace, para quién, cuánto cuesta y
 * a dónde escribir.
 *
 * Vive en la misma aplicación y no en un sitio aparte, para que todo el producto
 * se despliegue de una sola vez en Railway. Quien abre la raíz del dominio sin
 * sesión llega aquí (`bootstrap/app.php`); quien tiene sesión sigue yendo a su
 * inicio.
 *
 * Los planes salen de la tabla `planes` —activos, del más barato al más caro—,
 * así que cambiar un precio en el panel cambia esta página sin desplegar.
 */
class BienvenidaController extends Controller
{
    public function __invoke(Request $peticion, DocumentosLegales $documentos): Response
    {
        $implementacion = (array) config('comercial.implementacion');

        return Inertia::render('Publico/Bienvenida', [
            'planes' => Plan::query()
                ->where('activo', true)
                ->orderBy('precio_mensual')
                ->get()
                ->map(fn (Plan $plan): array => [
                    'nombre' => $plan->nombre,
                    'precio' => Formato::dinero($plan->precio_mensual),
                    'empleados' => (int) $plan->empleados_incluidos,
                    'usuarios' => (int) $plan->usuarios_incluidos,
                    'implementacion' => isset($implementacion[$plan->nombre])
                        ? Formato::dinero((string) $implementacion[$plan->nombre])
                        : null,
                ])
                ->values()
                ->all(),
            'usuarioAdicional' => Formato::dinero((string) config('comercial.usuario_adicional')),
            'correo' => (string) config('comercial.correo'),
            'documentos' => array_map(
                fn (DocumentoLegal $documento): array => ['titulo' => $documento->titulo, 'url' => $documento->url()],
                $documentos->todos(),
            ),
            'conSesion' => $peticion->user() !== null,
        ]);
    }
}
