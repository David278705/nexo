<?php

namespace App\Autenticacion;

use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Quién ya escribió su código en esta sesión.
 *
 * Es el único sitio donde se toca esa marca, y por eso se puede afirmar dos
 * cosas de una vez: que la verificación **dura lo que dura la sesión** —cerrar
 * sesión la borra, porque `session()->invalidate()` borra todo— y que la marca
 * es de una cuenta concreta y no de la sesión en abstracto.
 *
 * Guardar el identificador del usuario junto a la marca es lo que hace que
 * falle cerrado: si en la misma sesión llegara a haber otro usuario, la marca
 * del anterior no le sirve y se le vuelve a pedir el código.
 */
class SegundoFactorDeLaSesion
{
    private const CLAVE = 'segundo_factor.verificado_por';

    public function __construct(private readonly Session $sesion) {}

    public function verificada(User $usuario): bool
    {
        return $this->sesion->get(self::CLAVE) === $usuario->getKey();
    }

    public function marcarVerificada(User $usuario): void
    {
        $this->sesion->put(self::CLAVE, $usuario->getKey());
    }

    public function olvidar(): void
    {
        $this->sesion->forget(self::CLAVE);
    }
}
