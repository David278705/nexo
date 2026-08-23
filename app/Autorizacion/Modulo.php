<?php

namespace App\Autorizacion;

/**
 * Los módulos sobre los que se conceden permisos.
 *
 * Son los del menú de la sección 4.2 del documento de diseño, con una
 * precisión: las tres subsecciones de Novedades —incapacidades, vacaciones y
 * licencias— son módulos independientes, porque el capítulo 3 de la
 * especificación funcional dice expresamente que «una persona puede encargarse
 * solo de vacaciones». El menú vuelve a agruparlas bajo Novedades usando
 * `seccion()`.
 *
 * Dos módulos no se asignan nunca a mano y por eso no aparecen en
 * `asignables()`:
 *
 * - `Inicio` lo ve todo usuario de una empresa.
 * - `Configuracion` es solo del superadministrador.
 *
 * `Alertas` es el único que no está en aquella tabla 4.2, y no es un olvido: lo
 * pide el documento 2, sección 5.5, «panel de alertas dentro del sistema como
 * canal alterno», que se escribió después. Va en el grupo «Sistema», al lado de
 * Auditoría, porque es lo mismo que ella: un registro de lo que el sistema hizo,
 * de solo lectura. Tener permiso sobre él **no** abre las alertas de los demás
 * módulos: el panel enseña únicamente los tipos cuyo módulo el usuario ya tiene
 * (`App\Alertas\AlertasDelUsuario`).
 */
enum Modulo: string
{
    case Inicio = 'inicio';

    case Empleados = 'empleados';

    case Contratos = 'contratos';

    case Incapacidades = 'incapacidades';

    case Vacaciones = 'vacaciones';

    case Licencias = 'licencias';

    case Movimientos = 'movimientos';

    case Terminaciones = 'terminaciones';

    case Alertas = 'alertas';

    case Auditoria = 'auditoria';

    case Configuracion = 'configuracion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Inicio => 'Inicio',
            self::Empleados => 'Empleados',
            self::Contratos => 'Contratos',
            self::Incapacidades => 'Incapacidades',
            self::Vacaciones => 'Vacaciones',
            self::Licencias => 'Licencias y ausencias',
            self::Movimientos => 'Movimientos',
            self::Terminaciones => 'Terminaciones',
            self::Alertas => 'Alertas',
            self::Auditoria => 'Auditoría',
            self::Configuracion => 'Configuración',
        };
    }

    /**
     * La sección del menú donde vive el módulo.
     *
     * Coincide con la etiqueta salvo en los tres de novedades, que el menú
     * muestra agrupados aunque el permiso sea de cada uno por separado.
     */
    public function seccion(): string
    {
        return match ($this) {
            self::Incapacidades, self::Vacaciones, self::Licencias => 'Novedades',
            default => $this->etiqueta(),
        };
    }

    /**
     * El grupo bajo el que la barra lateral agrupa el módulo, como en
     * `prototipos/sistema.html`. `Inicio` va suelto arriba, sin encabezado.
     */
    public function grupo(): ?string
    {
        return match ($this) {
            self::Inicio => null,
            self::Empleados, self::Contratos => 'Personal',
            self::Incapacidades, self::Vacaciones, self::Licencias => 'Novedades',
            self::Movimientos, self::Terminaciones => 'Movimientos',
            self::Alertas, self::Auditoria, self::Configuracion => 'Sistema',
        };
    }

    /**
     * La dirección de la sección. `Inicio` es la raíz; el resto toma su propio
     * valor, para que el nombre del módulo, la ruta y el permiso sean el mismo
     * texto y no puedan separarse.
     */
    public function url(): string
    {
        return $this === self::Inicio ? '/' : '/'.$this->value;
    }

    /**
     * El nombre de la ruta de Laravel, que es el valor salvo en `Inicio`.
     */
    public function nombreDeRuta(): string
    {
        return $this->value;
    }

    /**
     * Lo ve todo usuario de una empresa, sin asignación previa.
     */
    public function esDeTodos(): bool
    {
        return $this === self::Inicio;
    }

    /**
     * Solo el superadministrador, por mucho que se le asigne a otro.
     */
    public function esSoloDelSuperadministrador(): bool
    {
        return $this === self::Configuracion;
    }

    /**
     * Los módulos que el superadministrador puede repartir entre sus
     * administradores.
     *
     * @return list<self>
     */
    public static function asignables(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $modulo) => ! $modulo->esDeTodos() && ! $modulo->esSoloDelSuperadministrador(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function valoresAsignables(): array
    {
        return array_map(fn (self $modulo) => $modulo->value, self::asignables());
    }
}
