<?php

namespace App\Contratacion;

/**
 * Con cuánta fuerza actúa una validación legal: impidiendo o advirtiendo.
 *
 * Es la decisión B.3 de `docs/06-decisiones-pendientes.md`, que no está cerrada
 * para todas las reglas. Mientras no lo esté, el estado de cada validación se lee
 * de `config/validaciones.php` y no se escribe dentro de la lógica: convertir una
 * advertencia en bloqueo es cambiar una palabra en la configuración, que es
 * exactamente lo que ese documento promete.
 *
 * De aquí sale también el color, porque las dos cosas son la misma: el rojo se
 * usa solo cuando algo está mal de verdad (documento 3, sección 3.1), y algo que
 * sí se puede guardar no está mal de verdad.
 */
enum EstadoDeValidacion: string
{
    /** No se puede guardar. */
    case Bloquea = 'bloquea';

    /** Se puede guardar, y la consecuencia queda dicha y documentada. */
    case Advierte = 'advierte';

    public function bloquea(): bool
    {
        return $this === self::Bloquea;
    }

    /**
     * El tono del aviso que lee el usuario.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Bloquea => 'error',
            self::Advierte => 'advertencia',
        };
    }

    /**
     * El estado configurado para esa validación.
     *
     * Bloquea si el valor configurado no es ninguno de los dos: ante una
     * configuración equivocada, la opción segura es no registrar algo que puede
     * ser ilegal.
     */
    public static function configurada(string $validacion): self
    {
        return self::tryFrom((string) config('validaciones.'.$validacion)) ?? self::Bloquea;
    }
}
