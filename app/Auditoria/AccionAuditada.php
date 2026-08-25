<?php

namespace App\Auditoria;

/**
 * Qué se le hizo al registro. Texto legible, no número: la tabla de auditoría
 * se lee tal cual, sin diccionario al lado.
 */
enum AccionAuditada: string
{
    case Creado = 'creado';

    case Editado = 'editado';

    /**
     * El borrado lógico, que es el único que existe en este sistema. Los
     * valores anteriores quedan guardados enteros: son lo que había justo antes
     * de que la fila desapareciera de las consultas.
     */
    case Eliminado = 'eliminado';

    case Restaurado = 'restaurado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Creado => 'Creado',
            self::Editado => 'Editado',
            self::Eliminado => 'Eliminado',
            self::Restaurado => 'Restaurado',
        };
    }

    /**
     * El tono de la etiqueta en la pantalla de auditoría (REV-04), como los del
     * documento 3, sección 3.4: rojo solo cuando algo se elimina de verdad.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Creado, self::Restaurado => 'exito',
            self::Editado => 'advertencia',
            self::Eliminado => 'error',
        };
    }
}
