<?php

namespace App\Licencias;

/**
 * Las tres situaciones del documento 1, 6.5, que se calculan igual y todas se
 * entienden como suspensión del contrato: licencia no remunerada, ausencia y
 * suspensión.
 */
enum TipoDeLicencia: string
{
    case LicenciaNoRemunerada = 'licencia_no_remunerada';

    case Ausencia = 'ausencia';

    case Suspension = 'suspension';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LicenciaNoRemunerada => 'Licencia no remunerada',
            self::Ausencia => 'Ausencia',
            self::Suspension => 'Suspensión',
        };
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function paraElegir(): array
    {
        return array_map(
            fn (self $tipo): array => ['valor' => $tipo->value, 'texto' => $tipo->etiqueta()],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $tipo): string => $tipo->value, self::cases());
    }
}
