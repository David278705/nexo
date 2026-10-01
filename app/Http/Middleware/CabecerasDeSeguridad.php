<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las cabeceras de seguridad que el navegador necesita para proteger a quien
 * usa el sistema. Este guarda diagnósticos médicos y nóminas: son lo mínimo.
 *
 * Va en la pila global, no solo en la web, para cubrir también las descargas
 * —exportaciones a Excel, certificados— y las páginas de error.
 *
 * **No hay una Content-Security-Policy completa**, a propósito: la única
 * directiva que se fija es `frame-ancestors`. Una política de scripts mal
 * calibrada deja la aplicación en blanco sin avisar, y lo que aquí se quiere
 * cerrar —que otra página la cargue dentro de un marco para engañar al usuario
 * con clics— lo cierra esa sola directiva. Ninguna pantalla del sistema usa
 * iframes, así que no se rompe nada.
 */
class CabecerasDeSeguridad
{
    /**
     * Un año. Es lo que piden los navegadores para tomarse HSTS en serio, y en
     * cuanto el dominio sirve HTTPS no hay vuelta atrás que proteger.
     */
    private const SEGUNDOS_DE_HSTS = 31536000;

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $respuesta = $siguiente($peticion);

        $cabeceras = $respuesta->headers;

        // Que nadie cargue el sistema dentro de un marco (clickjacking). Las dos
        // formas: la moderna y la que entienden los navegadores viejos.
        $cabeceras->set('Content-Security-Policy', "frame-ancestors 'none'");
        $cabeceras->set('X-Frame-Options', 'DENY');

        // Que el navegador no adivine el tipo de un archivo distinto del que
        // declara el servidor: un Excel no se ejecuta como script.
        $cabeceras->set('X-Content-Type-Options', 'nosniff');

        // Al seguir un enlace hacia afuera, no mandar la dirección completa:
        // las rutas llevan identificadores de empleados.
        $cabeceras->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $cabeceras->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Solo sobre HTTPS: mandarla sobre HTTP no tiene efecto y en local
        // dejaría al navegador exigiendo HTTPS a localhost.
        if ($peticion->isSecure()) {
            $cabeceras->set('Strict-Transport-Security', 'max-age='.self::SEGUNDOS_DE_HSTS.'; includeSubDomains');
        }

        return $respuesta;
    }
}
