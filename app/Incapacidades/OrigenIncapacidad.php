<?php

namespace App\Incapacidades;

/**
 * El origen de una incapacidad (documento 1, 6.3): común o laboral.
 */
enum OrigenIncapacidad: string
{
    case Comun = 'comun';

    case Laboral = 'laboral';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Comun => 'Común',
            self::Laboral => 'Laboral',
        };
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function paraElegir(): array
    {
        return array_map(
            fn (self $origen): array => ['valor' => $origen->value, 'texto' => $origen->etiqueta()],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $origen): string => $origen->value, self::cases());
    }
}
