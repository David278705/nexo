<?php

namespace App\CargaInicial;

use App\Models\Catalogo;

/**
 * Una columna de una plantilla: el título con el que viene escrita en el
 * archivo y el campo del sistema donde acaba su valor.
 *
 * Es dato, no comportamiento: aquí no se lee ninguna celda —eso es
 * `LecturaDeCelda`— ni se valida nada —eso son las reglas de la plantilla—.
 *
 * **El título es la llave.** Las seis plantillas le dicen al cliente «no
 * agregue, quite ni reordene columnas, y no cambie los títulos de la fila 1: el
 * sistema los busca por nombre». Buscarlas por posición sería más corto de
 * programar y convertiría una columna insertada por descuido en veintiocho
 * valores corridos un puesto, cada uno en el campo del vecino.
 *
 * **Aquí no está si la columna es obligatoria.** La obligatoriedad vive en las
 * reglas de validación de cada plantilla, con el resto de las condiciones, para
 * que exista un solo sitio donde mirar por qué una fila se rechazó.
 */
final class Columna
{
    /**
     * @param  list<array{valor: string, texto: string}>  $opciones  las de un enumerado, tal como las ofrece el formulario
     * @param  array<string, string>  $sinonimos  otras formas de escribir una opción, admitidas por la propia plantilla
     * @param  class-string<Catalogo>|null  $catalogo
     */
    private function __construct(
        public readonly string $titulo,
        public readonly string $campo,
        public readonly TipoDeCelda $tipo,
        public readonly array $opciones = [],
        public readonly array $sinonimos = [],
        public readonly ?string $catalogo = null,
    ) {}

    public static function texto(string $titulo, string $campo): self
    {
        return new self($titulo, $campo, TipoDeCelda::Texto);
    }

    public static function fecha(string $titulo, string $campo): self
    {
        return new self($titulo, $campo, TipoDeCelda::Fecha);
    }

    public static function entero(string $titulo, string $campo): self
    {
        return new self($titulo, $campo, TipoDeCelda::Entero);
    }

    public static function decimal(string $titulo, string $campo): self
    {
        return new self($titulo, $campo, TipoDeCelda::Decimal);
    }

    /**
     * Una columna de pesos: sale leída como texto de dos decimales, nunca como
     * coma flotante.
     */
    public static function dinero(string $titulo, string $campo): self
    {
        return new self($titulo, $campo, TipoDeCelda::Dinero);
    }

    public static function siNo(string $titulo, string $campo): self
    {
        return new self($titulo, $campo, TipoDeCelda::SiNo);
    }

    /**
     * Una columna que se resuelve contra un enumerado del sistema.
     *
     * Las opciones llegan de `paraElegir()`, el mismo método que llena el
     * selector del formulario. Es lo que hace imposible que la carga admita un
     * valor que la pantalla no ofrece, o al revés.
     *
     * @param  list<array{valor: string, texto: string}>  $opciones
     * @param  array<string, string>  $sinonimos
     */
    public static function lista(string $titulo, string $campo, array $opciones, array $sinonimos = []): self
    {
        return new self($titulo, $campo, TipoDeCelda::Lista, $opciones, $sinonimos);
    }

    /**
     * Una columna que se resuelve contra una de las cinco tablas de entidades.
     *
     * @param  class-string<Catalogo>  $catalogo
     */
    public static function catalogo(string $titulo, string $campo, string $catalogo): self
    {
        return new self($titulo, $campo, TipoDeCelda::Catalogo, catalogo: $catalogo);
    }

    /**
     * Los textos que el cliente puede escribir en esta columna, para el mensaje
     * de error de la fila que no acertó ninguno.
     *
     * @return list<string>
     */
    public function textosAdmitidos(): array
    {
        return array_map(
            fn (array $opcion): string => $opcion['texto'],
            $this->opciones,
        );
    }
}
