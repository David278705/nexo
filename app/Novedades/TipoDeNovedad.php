<?php

namespace App\Novedades;

use App\Autorizacion\Modulo;

/**
 * Las cinco clases de novedad que comparten el flujo de registro (documento 3,
 * sección 5.5 y tabla 5.6).
 *
 * Existe por una sola razón: que el flujo sea **idéntico en los cinco módulos**.
 * El título del modal, el módulo sobre el que se pide permiso y el nombre con
 * que se le habla al usuario viven aquí y en ningún otro sitio, así que ninguno
 * de los cinco puede quedar diciendo algo distinto.
 *
 * Los cinco títulos son los literales de `prototipos/sistema.html` —la constante
 * `TITULOS` de su flujo de novedad—, y una prueba comprueba que siguen siendo
 * esos.
 *
 * No es un enumerado que se guarde en ninguna columna: las novedades se guardan
 * cada una en su tabla, que es su módulo. Este enumerado es del flujo, no del
 * dato.
 */
enum TipoDeNovedad: string
{
    case Incapacidad = 'incapacidad';

    case Vacaciones = 'vacaciones';

    case Licencia = 'licencia';

    case Movimiento = 'movimiento';

    case Terminacion = 'terminacion';

    /**
     * El módulo sobre el que se conceden los permisos de esta novedad.
     */
    public function modulo(): Modulo
    {
        return match ($this) {
            self::Incapacidad => Modulo::Incapacidades,
            self::Vacaciones => Modulo::Vacaciones,
            self::Licencia => Modulo::Licencias,
            self::Movimiento => Modulo::Movimientos,
            self::Terminacion => Modulo::Terminaciones,
        };
    }

    /**
     * El título del modal, igual en el pequeño y en el grande: el flujo es uno
     * solo y el usuario no cambió de tarea al pasar de un paso al otro.
     */
    public function tituloDelModal(): string
    {
        return match ($this) {
            self::Incapacidad => 'Nueva incapacidad',
            self::Vacaciones => 'Registrar vacaciones',
            self::Licencia => 'Nueva licencia o ausencia',
            self::Movimiento => 'Nuevo movimiento salarial',
            self::Terminacion => 'Registrar terminación',
        };
    }

    /**
     * El título del modal cuando lo que se abre es un registro que ya existe
     * (documento 1, 6.2).
     *
     * Editar y registrar son dos cosas distintas y el modal tiene que decirlo:
     * es la misma regla de nombres que separa «Renovar contrato» de «Editar la
     * fecha de finalización» (documento 3, 2.4).
     *
     * **Los cinco lo tienen desde REV-03.** Movimientos y terminaciones se
     * quedaron sin él en F2-10 porque deshacerlos —devolver el salario
     * anterior, revivir a un empleado inactivo— no lo definía ningún documento;
     * lo definió David y está escrito en la tarea REV-03 del backlog.
     */
    public function tituloDeEdicion(): string
    {
        return match ($this) {
            self::Incapacidad => 'Editar incapacidad',
            self::Vacaciones => 'Editar vacaciones',
            self::Licencia => 'Editar licencia o ausencia',
            self::Movimiento => 'Editar movimiento salarial',
            self::Terminacion => 'Editar terminación',
        };
    }

    /**
     * Los cinco módulos que registran novedades por este flujo.
     *
     * Es lo que decide si a un usuario se le puede resolver una búsqueda de
     * empleado para novedad: quien no puede registrar ninguna de las cinco no
     * tiene por qué consultar la ficha de nadie por este camino.
     *
     * @return list<Modulo>
     */
    public static function modulos(): array
    {
        return array_map(fn (self $tipo): Modulo => $tipo->modulo(), self::cases());
    }

    /**
     * Lo que la interfaz necesita saber de un tipo de novedad.
     *
     * @return array{clave: string, titulo: string, tituloEdicion: string, modulo: string}
     */
    public function paraLaPantalla(): array
    {
        return [
            'clave' => $this->value,
            'titulo' => $this->tituloDelModal(),
            'tituloEdicion' => $this->tituloDeEdicion(),
            'modulo' => $this->modulo()->value,
        ];
    }
}
