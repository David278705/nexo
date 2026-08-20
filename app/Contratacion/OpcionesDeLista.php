<?php

namespace App\Contratacion;

/**
 * Lo que todo enumerado de este dominio hace para llegar a un `Selector`.
 *
 * Cada lista del formulario necesita las mismas dos cosas: las opciones con su
 * texto legible para la pantalla y los valores sueltos para `Rule::in()`. Se
 * escribieron una vez por enumerado hasta que fueron seis; a partir de las
 * cuatro listas del perfil sociodemográfico viven aquí.
 *
 * El enumerado que lo use solo tiene que aportar `etiqueta()`.
 */
trait OpcionesDeLista
{
    abstract public function etiqueta(): string;

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function paraElegir(): array
    {
        return array_map(
            fn (self $caso): array => ['valor' => $caso->value, 'texto' => $caso->etiqueta()],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $caso): string => $caso->value, self::cases());
    }
}
