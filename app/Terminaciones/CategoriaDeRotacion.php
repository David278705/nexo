<?php

namespace App\Terminaciones;

/**
 * La categoría macro a la que pertenece cada tipo de salida (documento 1, 7.4).
 *
 * **El usuario no la digita: la deduce el sistema.** Por eso no es una columna
 * ni una lista del formulario; se pregunta a `TipoDeSalida::categoriaDeRotacion()`
 * cada vez que hace falta. Guardarla sería guardar algo derivado, y el día que
 * Angela mueva un tipo de una categoría a otra —el mutuo acuerdo es justo el
 * caso que el documento discute— las filas viejas quedarían clasificadas con el
 * criterio antiguo.
 */
enum CategoriaDeRotacion: string
{
    case Voluntaria = 'voluntaria';

    case Involuntaria = 'involuntaria';

    case FinNatural = 'fin_natural';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Voluntaria => 'Rotación voluntaria',
            self::Involuntaria => 'Rotación involuntaria',
            self::FinNatural => 'Fin natural del vínculo',
        };
    }

    /**
     * El nombre corto, que es el que cabe en la columna «Rotación» del listado
     * y el que usa el prototipo: «Voluntaria», «Involuntaria», «Fin natural».
     */
    public function nombreCorto(): string
    {
        return match ($this) {
            self::Voluntaria => 'Voluntaria',
            self::Involuntaria => 'Involuntaria',
            self::FinNatural => 'Fin natural',
        };
    }

    /**
     * El tono de la etiqueta de color del listado.
     *
     * **El prototipo pinta la rotación involuntaria de rojo y aquí es ámbar**,
     * y es deliberado: la regla de interfaz dice que el rojo se reserva para
     * cuando algo está mal de verdad, y una salida decidida por la empresa es
     * un hecho que merece atención, no un error del sistema. Los tres tonos
     * siguen siendo distintos entre sí y el texto va siempre escrito, así que
     * el color no es el único portador de la información.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Voluntaria => 'neutro',
            self::Involuntaria => 'advertencia',
            self::FinNatural => 'exito',
        };
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function paraElegir(): array
    {
        return array_map(
            fn (self $categoria): array => ['valor' => $categoria->value, 'texto' => $categoria->nombreCorto()],
            self::cases(),
        );
    }
}
