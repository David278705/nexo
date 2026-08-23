<?php

namespace App\CargaInicial\Plantillas;

use App\CargaInicial\Columna;
use App\CargaInicial\FilaTraducida;
use App\Http\Requests\RegistrarVacacion;

/**
 * La plantilla 4, «Histórico de vacaciones» (documento 1, 6.4).
 *
 * Es la única de las seis cuya hoja de instrucciones advierte que **es mejor
 * dejarla vacía que rellenarla a ojo**: si la empresa no tiene registradas las
 * fechas reales de los periodos disfrutados, basta con el saldo de hoy de la
 * plantilla de empleados, y el sistema sigue acumulando bien desde ahí. Ese
 * aviso es también la razón de que la regla 2 del proyecto exista: el saldo se
 * calcula, así que un periodo inventado aquí saldría restado en pantalla.
 *
 * Tampoco trae columna de días: se cuentan en días hábiles, con los festivos
 * del año y el parámetro de sábado laboral de la empresa.
 */
class PlantillaDeVacaciones extends PlantillaDeHistorico
{
    public function clave(): string
    {
        return 'vacaciones';
    }

    public function nombre(): string
    {
        return 'Histórico de vacaciones';
    }

    public function hoja(): string
    {
        return 'Vacaciones';
    }

    public function columnas(): array
    {
        return [
            $this->documento(),
            Columna::fecha('Día inicial de vacaciones', 'fecha_inicio'),
            Columna::fecha('Día final de vacaciones', 'fecha_fin'),
        ];
    }

    public function reglas(FilaTraducida $fila): array
    {
        return [
            ...$this->reglaDelDocumento(),
            ...$this->formulario($fila)->rules(),
        ];
    }

    public function atributos(): array
    {
        return [
            'numero_documento' => 'número de documento',
            ...$this->formulario(new FilaTraducida(0))->attributes(),
        ];
    }

    public function mensajes(): array
    {
        return $this->formulario(new FilaTraducida(0))->messages();
    }

    private function formulario(FilaTraducida $fila): RegistrarVacacion
    {
        return RegistrarVacacion::create('/', 'POST', $fila->valores());
    }
}
