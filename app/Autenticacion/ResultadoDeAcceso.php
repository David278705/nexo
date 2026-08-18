<?php

namespace App\Autenticacion;

/**
 * Cómo terminó un intento de entrar. Se guarda como texto legible, no como
 * número: quien lea la tabla de accesos tiene que entenderla sin un diccionario.
 */
enum ResultadoDeAcceso: string
{
    case Exitoso = 'exitoso';

    case Fallido = 'fallido';

    /**
     * El intento ni siquiera se comprobó: llegó con la espera por intentos
     * fallidos todavía corriendo. Se distingue del fallido porque son señales
     * distintas: uno es una contraseña mal escrita, el otro es insistencia.
     */
    case Bloqueado = 'bloqueado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Exitoso => 'Exitoso',
            self::Fallido => 'Fallido',
            self::Bloqueado => 'Bloqueado',
        };
    }
}
