<?php

namespace App\Contratacion;

/**
 * Los documentos de identidad con los que se puede registrar a una persona,
 * de la tabla de identificación personal del documento 1, sección 5.1.
 *
 * La tarjeta de identidad está en la lista a propósito y no sobra: en Colombia
 * los menores de edad pueden trabajar con autorización, y sin ella no se les
 * podría registrar.
 *
 * Se guarda el valor, que es texto legible, no un número. Un reporte exportado
 * tiene que poder leerse sin un diccionario al lado.
 */
enum TipoDocumento: string
{
    case CedulaCiudadania = 'cedula_ciudadania';

    case TarjetaIdentidad = 'tarjeta_identidad';

    case CedulaExtranjeria = 'cedula_extranjeria';

    case Pasaporte = 'pasaporte';

    case Ppt = 'ppt';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CedulaCiudadania => 'Cédula de ciudadanía',
            self::TarjetaIdentidad => 'Tarjeta de identidad',
            self::CedulaExtranjeria => 'Cédula de extranjería',
            self::Pasaporte => 'Pasaporte',
            self::Ppt => 'PPT',
        };
    }

    /**
     * Las opciones tal como las consume un selector del formulario.
     *
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
