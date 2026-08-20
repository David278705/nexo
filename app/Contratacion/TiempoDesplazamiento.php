<?php

namespace App\Contratacion;

/**
 * El tiempo de desplazamiento al trabajo del perfil sociodemográfico
 * (documento 1, 5.2).
 *
 * Los cinco rangos son los de la columna «Tiempo de Desplazamiento» de
 * `plantillas/Listas_Desplegables_Formulario_Empleados.xlsx`. Ese archivo
 * explica por qué son rangos y no minutos: para que el campo se comporte como
 * las demás listas desplegables. Cambiarlo a un numérico es una decisión de
 * Angela, no del código.
 */
enum TiempoDesplazamiento: string
{
    use OpcionesDeLista;

    case MenosDe15Minutos = 'menos_de_15_minutos';

    case Entre15Y30Minutos = 'entre_15_y_30_minutos';

    case Entre30Y60Minutos = 'entre_30_y_60_minutos';

    case Entre1Y2Horas = 'entre_1_y_2_horas';

    case MasDe2Horas = 'mas_de_2_horas';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MenosDe15Minutos => 'Menos de 15 minutos',
            self::Entre15Y30Minutos => 'Entre 15 y 30 minutos',
            self::Entre30Y60Minutos => 'Entre 30 y 60 minutos',
            self::Entre1Y2Horas => 'Entre 1 y 2 horas',
            self::MasDe2Horas => 'Más de 2 horas',
        };
    }
}
