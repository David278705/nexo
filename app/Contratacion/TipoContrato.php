<?php

namespace App\Contratacion;

/**
 * Las tres modalidades de contrato que maneja el sistema (documento 1, 5.4).
 *
 * Son tres y solo tres. «Obra o labor» es **una sola opción, no dos**, y los
 * contratos de prestación de servicios quedan expresamente fuera: no son
 * laborales sino comerciales, y ninguna regla de este sistema les aplica.
 */
enum TipoContrato: string
{
    case Indefinido = 'indefinido';

    case Fijo = 'fijo';

    case ObraLabor = 'obra_labor';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Indefinido => 'Término indefinido',
            self::Fijo => 'Término fijo',
            self::ObraLabor => 'Obra o labor',
        };
    }

    /**
     * Si la modalidad exige fecha de finalización.
     *
     * Solo el término fijo la tiene. En indefinido y en obra o labor la fecha
     * queda vacía: el primero no termina por plazo y el segundo termina cuando
     * termina la obra, que no es una fecha que se sepa de antemano.
     */
    public function exigeFechaFin(): bool
    {
        return $this === self::Fijo;
    }

    /**
     * Si sobre esta modalidad se ofrecen renovar y cambiar a indefinido.
     *
     * Ambas acciones son exclusivas del término fijo (documento 1, 5.5). En
     * obra o labor el paso a indefinido se hace por otrosí, fuera del sistema.
     */
    public function admiteRenovacion(): bool
    {
        return $this === self::Fijo;
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
