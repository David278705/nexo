<?php

namespace App\Http\Middleware;

use App\Proveedor\AccesoAlEntorno;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras se está dentro del entorno de un cliente, el panel del proveedor no
 * se puede pedir (documento 4, 4.4).
 *
 * No es una restricción de seguridad —quien está dentro es el `owner` y el panel
 * es suyo—, es evitar un estado a medias: durante una visita el contexto de la
 * petición es el de la empresa visitada, así que el panel se estaría mirando
 * «desde dentro» de un cliente. O se está en el panel, o se está dentro de una
 * empresa, nunca en los dos sitios a la vez.
 *
 * El camino de vuelta existe siempre y por eso no encierra a nadie: salir del
 * entorno vive fuera del prefijo del panel, y salir cierra la visita en el
 * registro en lugar de dejarla abierta para siempre.
 */
class FueraDeUnaVisita
{
    public function __construct(private readonly AccesoAlEntorno $acceso) {}

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        if ($this->acceso->enCurso() === null) {
            return $siguiente($peticion);
        }

        return redirect('/')->with('estado', AccesoAlEntorno::PANEL_BLOQUEADO);
    }
}
