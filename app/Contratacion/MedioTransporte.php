<?php

namespace App\Contratacion;

/**
 * El medio de transporte al trabajo del perfil sociodemográfico (documento 1,
 * 5.2).
 *
 * Las siete opciones son las de la columna «Medio de Transporte» de
 * `plantillas/Listas_Desplegables_Formulario_Empleados.xlsx`.
 *
 * No es un dato de curiosidad: junto con el tiempo de desplazamiento es lo que
 * el SG-SST mira para el riesgo de accidente de tránsito en el trayecto.
 */
enum MedioTransporte: string
{
    use OpcionesDeLista;

    case APie = 'a_pie';

    case Bicicleta = 'bicicleta';

    case Motocicleta = 'motocicleta';

    case VehiculoParticular = 'vehiculo_particular';

    case TransportePublico = 'transporte_publico';

    case TransporteDeLaEmpresa = 'transporte_de_la_empresa';

    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::APie => 'A pie',
            self::Bicicleta => 'Bicicleta',
            self::Motocicleta => 'Motocicleta',
            self::VehiculoParticular => 'Vehículo particular',
            self::TransportePublico => 'Transporte público',
            self::TransporteDeLaEmpresa => 'Transporte de la empresa',
            self::Otro => 'Otro',
        };
    }
}
