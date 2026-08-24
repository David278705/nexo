<?php

namespace App\Presentacion;

use Throwable;

/**
 * Los tres textos del documento 3, 6.3 que no nacen de una acción de negocio
 * con su propio mensaje: «Sin permiso», «Sesión expirada» y «Error
 * inesperado». Los usa `bootstrap/app.php` para que esas tres respuestas no
 * las conteste la página de Laravel —en inglés la de una autorización sin
 * motivo de negocio, con traza de depuración la de un error real—, que es
 * justo lo que prohíbe F6-02.
 */
final class TextoDeError
{
    public const SIN_PERMISO = 'No tienes acceso a esta sección. Si crees que deberías tenerlo, '
        .'habla con el administrador de tu empresa.';

    public const SESION_EXPIRADA = 'Tu sesión terminó por inactividad. Vuelve a entrar para continuar.';

    public const ERROR_INESPERADO = 'Algo salió mal de nuestro lado. Ya nos enteramos y lo estamos '
        .'revisando. Intenta de nuevo en unos minutos.';

    /**
     * El texto de un 403: el de la regla de negocio si la excepción ya lo
     * trae —como el modo consulta del entorno del cliente (F5-07)—, porque
     * es más preciso que el genérico. `bootstrap/app.php` ya se encargó de
     * que una autorización denegada sin motivo propio llegue aquí con
     * `SIN_PERMISO` como mensaje, así que un mensaje vacío es el único caso
     * que le falta resolver a este método: el de un `abort(403)` sin texto.
     */
    public static function paraSinPermiso(Throwable $error): string
    {
        $mensaje = $error->getMessage();

        return $mensaje !== '' ? $mensaje : self::SIN_PERMISO;
    }
}
