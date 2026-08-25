<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Proveedor\AccesoAlEntorno;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La puerta simétrica de `SoloProveedor`: el `owner` no tiene nada que hacer
 * en el entorno del cliente y aquí recibe 403, igual que el documento 4, 4.1
 * dice que debe pasarle en «todas las secciones» —**salvo durante una visita
 * registrada** (documento 4, 4.4), que es la misma excepción que ya aplica
 * `PoliticaDeModulos` para el resto de las secciones.
 *
 * Casi todas las secciones ya lo cumplen solas: cada una declara su módulo
 * con `modulo:`, y `PoliticaDeModulos` le niega al `owner` los módulos de
 * cualquier empresa salvo durante esa visita. La única que no lleva
 * `modulo:` —a propósito, porque es de todo usuario de una empresa— es `/`,
 * el panel de inicio, y por eso es la única que necesita esta puerta aparte.
 * Es además la ruta a la que `EntornoDelClienteController::entrar()` manda
 * al owner **al empezar** una visita, así que bloquearlo sin la excepción
 * rompería esa función ya construida.
 *
 * Sin esta puerta, un `owner` que navegue a `/` sin estar de visita no
 * recibe 403: ve una pantalla vacía y confusa, porque no pertenece a ninguna
 * empresa y no hay nada que contar. No es una fuga de datos —la consulta
 * simplemente no tiene tenant que mirar—, pero contradice lo que el propio
 * proyecto documenta que debe pasar.
 */
class SoloCliente
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if ($usuario instanceof User
            && $usuario->esOwner()
            && app(AccesoAlEntorno::class)->enCurso() === null) {
            abort(403);
        }

        return $siguiente($peticion);
    }
}
