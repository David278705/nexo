<?php

namespace App\Contratacion;

/**
 * El nivel educativo alcanzado del perfil sociodemográfico (documento 1, 5.2).
 *
 * Son las seis opciones de la columna «Nivel Educativo» de
 * `plantillas/Listas_Desplegables_Formulario_Empleados.xlsx`.
 *
 * Dos cosas que ese archivo dice de sí mismo, en su hoja «Notas y fuentes», y
 * que conviene no perder: **técnico y tecnólogo van unidos** en una sola
 * opción, y **«Posgraduado(a)» no distingue especialización, maestría ni
 * doctorado**. Separarlos después es agregar casos aquí y nada más: lo guardado
 * es texto legible, no un número de orden.
 */
enum NivelEducativo: string
{
    use OpcionesDeLista;

    case Ninguno = 'ninguno';

    case BasicaPrimaria = 'basica_primaria';

    case Bachiller = 'bachiller';

    case TecnicoTecnologo = 'tecnico_tecnologo';

    case Profesional = 'profesional';

    case Posgraduado = 'posgraduado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ninguno => 'Ninguno',
            self::BasicaPrimaria => 'Básica Primaria',
            self::Bachiller => 'Bachiller',
            self::TecnicoTecnologo => 'Técnico / Tecnólogo',
            self::Profesional => 'Profesional',
            self::Posgraduado => 'Posgraduado(a)',
        };
    }
}
