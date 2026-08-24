<?php

namespace App\Http\Middleware;

use App\Proveedor\AccesoAlEntorno;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La segunda condición no negociable del documento 4, 4.4: el acceso del
 * proveedor al entorno de un cliente **es de solo lectura por defecto**.
 *
 * Se cumple aquí, en el servidor y en cada petición, y no escondiendo botones:
 * esconder el botón de eliminar no impide mandar el `DELETE` a mano, y quien
 * está dentro es el `owner`, que sabe la dirección.
 *
 * La regla es la del verbo y no la de la pantalla, para que no haya que
 * acordarse de proteger cada módulo nuevo: **si la visita no tiene la escritura
 * activada, solo pasan los métodos de lectura**. Un módulo que se construya
 * dentro de un año queda protegido sin tocar este archivo.
 *
 * Dos direcciones quedan fuera y las dos son de salida, no de datos del cliente:
 * cerrar la visita y cerrar sesión. Sin la primera, el proveedor no podría salir
 * del entorno del cliente y la visita quedaría abierta para siempre en el
 * registro.
 */
class EntornoDeSoloLectura
{
    /**
     * @var list<string>
     */
    private const METODOS_DE_LECTURA = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @var list<string>
     */
    private const PERMITIDAS = ['entorno.salir', 'acceso.salir'];

    public function __construct(private readonly AccesoAlEntorno $acceso) {}

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        if ($this->esInofensiva($peticion) || $this->acceso->enCurso() === null) {
            return $siguiente($peticion);
        }

        abort_if(! $this->acceso->permiteEscribir(), 403, AccesoAlEntorno::SOLO_LECTURA);

        return $siguiente($peticion);
    }

    private function esInofensiva(Request $peticion): bool
    {
        return in_array($peticion->method(), self::METODOS_DE_LECTURA, true)
            || in_array($peticion->route()?->getName(), self::PERMITIDAS, true);
    }
}
