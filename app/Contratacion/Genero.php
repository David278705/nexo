<?php

namespace App\Contratacion;

/**
 * Las opciones de género del formulario de empleado (documento 1, 5.1).
 *
 * No se inventaron: son las dos que trae la columna «Género» de
 * `plantillas/Listas_Desplegables_Formulario_Empleados.xlsx`, que es la
 * respuesta de Angela a la decisión D.5. Ampliarlas es cambiar ese archivo y
 * este enumerado, no solo este enumerado.
 *
 * La columna `personas.genero` admite nulos porque nació antes que esta lista.
 * La obligatoriedad se aplica donde se aplica todo en este proyecto: en la
 * validación del servidor.
 */
enum Genero: string
{
    case Femenino = 'femenino';

    case Masculino = 'masculino';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Femenino => 'Femenino',
            self::Masculino => 'Masculino',
        };
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function paraElegir(): array
    {
        return array_map(
            fn (self $genero): array => ['valor' => $genero->value, 'texto' => $genero->etiqueta()],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $genero): string => $genero->value, self::cases());
    }
}
