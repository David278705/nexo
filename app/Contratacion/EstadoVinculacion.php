<?php

namespace App\Contratacion;

/**
 * El estado del empleado, sección 5.3 del documento 1.
 *
 * **No lo digita nadie.** Al crear la vinculación queda activo, y pasa a
 * inactivo únicamente cuando se registra la salida en el módulo de
 * terminaciones. Por eso el enumerado no ofrece un método para cambiarlo a mano:
 * quien lo cambia es el hecho de registrar una terminación.
 *
 * Sobre una vinculación inactiva no se registran novedades y el sistema deja de
 * generar alertas.
 */
enum EstadoVinculacion: string
{
    case Activo = 'activo';

    case Inactivo = 'inactivo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Inactivo => 'Inactivo',
        };
    }

    public function estaActivo(): bool
    {
        return $this === self::Activo;
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function paraElegir(): array
    {
        return array_map(
            fn (self $estado): array => ['valor' => $estado->value, 'texto' => $estado->etiqueta()],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $estado): string => $estado->value, self::cases());
    }
}
