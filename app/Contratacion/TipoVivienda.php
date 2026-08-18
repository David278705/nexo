<?php

namespace App\Contratacion;

/**
 * El tipo de vivienda del perfil sociodemográfico (documento 1, 5.2).
 *
 * Es de las pocas listas de ese bloque cuyas opciones están decididas en el
 * propio documento: propia, arrendada o familiar. Las demás —estado civil,
 * nivel educativo, medio de transporte y tiempo de desplazamiento— esperan la
 * decisión D.5 y por eso son texto y no enumerado.
 *
 * Como todo el bloque, el campo es opcional aunque la empresa lo tenga activo.
 */
enum TipoVivienda: string
{
    case Propia = 'propia';

    case Arrendada = 'arrendada';

    case Familiar = 'familiar';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Propia => 'Propia',
            self::Arrendada => 'Arrendada',
            self::Familiar => 'Familiar',
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
