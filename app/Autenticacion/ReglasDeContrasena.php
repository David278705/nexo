<?php

namespace App\Autenticacion;

use Illuminate\Validation\Rules\Password;

/**
 * Lo que se le exige a una contraseña, en un solo sitio.
 *
 * Las tres pantallas que definen contraseña —restablecer, aceptar invitación y,
 * más adelante, cambiarla desde el perfil— comparten esta regla. Si cada una
 * escribiera la suya, tarde o temprano una sería más laxa que las otras.
 *
 * El documento de arquitectura (7.1) pide «longitud mínima» sin fijar el
 * número; ocho caracteres con letras y números es el mínimo de Laravel y el
 * criterio corriente. La confirmación del campo la pone el formulario con
 * `confirmed`, no esta regla.
 */
class ReglasDeContrasena
{
    public const LONGITUD_MINIMA = 8;

    /**
     * @return list<Password|string>
     */
    public static function reglas(): array
    {
        return [
            'required',
            'confirmed',
            Password::min(self::LONGITUD_MINIMA)->letters()->numbers(),
        ];
    }
}
