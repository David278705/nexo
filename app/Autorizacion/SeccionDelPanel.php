<?php

namespace App\Autorizacion;

/**
 * Las nueve secciones del panel del proveedor, tal como las lista el capítulo 2
 * del documento 4 y las agrupa `prototipos/panel-proveedor.html`.
 *
 * Es el equivalente de `Modulo` para el otro lado del sistema, con una
 * diferencia de fondo: **aquí no hay permisos por sección.** Al panel entra solo
 * el rol `owner` y entra a todo. Los dos niveles internos del equipo —acceso
 * completo y acceso de soporte sin datos de empleados ni facturación— son del
 * capítulo 10 y llegan con la sección «Equipo»; cuando existan, el filtro se
 * escribe aquí y no en la barra lateral.
 */
enum SeccionDelPanel: string
{
    /**
     * El prefijo bajo el que vive todo el panel.
     *
     * El documento 4 lo dice en su encabezado: «es una aplicación aparte de la
     * que ve el cliente, vive en otra dirección web». Mientras las dos compartan
     * dominio, esa dirección aparte es este prefijo, y es el único sitio donde
     * está escrito.
     */
    public const PREFIJO = 'proveedor';

    case Inicio = 'inicio';

    case Empresas = 'empresas';

    case PuestaEnMarcha = 'puesta-en-marcha';

    case Suscripciones = 'suscripciones';

    case Alertas = 'alertas';

    case Catalogos = 'catalogos';

    case Salud = 'salud';

    case Demostracion = 'demostracion';

    case Equipo = 'equipo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Inicio => 'Inicio',
            self::Empresas => 'Empresas',
            self::PuestaEnMarcha => 'Puesta en marcha',
            self::Suscripciones => 'Suscripciones',
            self::Alertas => 'Alertas enviadas',
            self::Catalogos => 'Catálogos',
            self::Salud => 'Salud del sistema',
            self::Demostracion => 'Demostración',
            self::Equipo => 'Equipo',
        };
    }

    /**
     * Para qué sirve cada sección, con las palabras de la tabla del capítulo 2.
     *
     * Se enseña en la pantalla de la sección mientras no tenga contenido: quien
     * abre el panel y encuentra nueve pantallas vacías necesita saber qué va a
     * vivir en cada una.
     */
    public function descripcion(): string
    {
        return match ($this) {
            self::Inicio => 'El estado del negocio y lo que requiere atención hoy.',
            self::Empresas => 'Listado de clientes, creación, parametrización y ficha de cada uno.',
            self::PuestaEnMarcha => 'El asistente de carga inicial desde las plantillas de Excel.',
            self::Suscripciones => 'Planes, cobros, mora y suspensiones.',
            self::Alertas => 'Consola de todo lo que el sistema ha enviado.',
            self::Catalogos => 'EPS, fondos, ARL, cajas, bancos, ciudades y festivos.',
            self::Salud => 'Procesos programados, errores y copias de seguridad.',
            self::Demostracion => 'Estado del ambiente de demostración y su restauración.',
            self::Equipo => 'Los usuarios internos y qué puede hacer cada uno.',
        };
    }

    /**
     * El grupo del menú, como en el prototipo. `Inicio` va suelto arriba.
     */
    public function grupo(): ?string
    {
        return match ($this) {
            self::Inicio => null,
            self::Empresas, self::PuestaEnMarcha, self::Suscripciones => 'Clientes',
            self::Alertas, self::Catalogos, self::Salud => 'Sistema',
            self::Demostracion, self::Equipo => 'Interno',
        };
    }

    public function url(): string
    {
        return $this === self::Inicio
            ? '/'.self::PREFIJO
            : '/'.self::PREFIJO.'/'.$this->value;
    }

    /**
     * El nombre de la ruta de Laravel. Igual que en el entorno del cliente, el
     * nombre de la sección, su dirección y su ruta son el mismo texto.
     */
    public function nombreDeRuta(): string
    {
        return self::PREFIJO.'.'.$this->value;
    }

    /**
     * Las secciones que todavía no tienen contenido propio.
     *
     * Van saliendo de aquí a medida que se construyen: `Inicio` nunca estuvo y
     * `Empresas` salió con su listado y su ficha (F1B-03).
     *
     * @return list<self>
     */
    public static function pendientes(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $seccion) => ! in_array($seccion, [self::Inicio, self::Empresas], true),
        ));
    }
}
