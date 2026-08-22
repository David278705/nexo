<?php

namespace App\Terminaciones;

use App\Models\Vinculacion;

/**
 * La confirmación que el documento 3, 5.6 exige antes de guardar una
 * terminación: «explicando que el empleado pasará a inactivo y dejarán de
 * generarse alertas».
 *
 * No es un «¿está seguro?» sino la consecuencia escrita, que es la regla de las
 * acciones con efecto (documento 3, 6.2). El texto es el del prototipo y vive
 * en el servidor —no en el componente— por lo mismo que las frases del cambio a
 * término indefinido: así la prueba que comprueba el efecto puede leer la misma
 * frase que ve el usuario, y el nombre que aparece en ella sale de la
 * vinculación de verdad y no de lo que el navegador tuviera en pantalla.
 */
final class AvisoDeTerminacion
{
    public static function para(Vinculacion $vinculacion): string
    {
        $nombre = $vinculacion->persona->nombreCompleto();

        return 'Al guardar, '.$nombre.' pasará a estado inactivo y el sistema dejará de generar '
            .'alertas sobre esta persona. El registro cierra el ciclo de esta vinculación.';
    }
}
