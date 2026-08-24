<?php

namespace App\CargaInicial\Plantillas;

use App\CargaInicial\Columna;
use App\CargaInicial\FilaTraducida;
use App\Http\Requests\RegistrarLicencia;
use App\Licencias\TipoDeLicencia;
use Illuminate\Support\Carbon;

/**
 * La plantilla 5, «Histórico de licencias, ausencias y suspensiones»
 * (documento 1, 6.5).
 *
 * Las tres suspenden el contrato y se tratan igual, así que van en una sola
 * tabla con un campo de clasificación, exactamente como en el módulo.
 *
 * Su hoja de instrucciones trae la advertencia que más conviene no perder de
 * vista al cargar: si la empresa tiene activo el parámetro correspondiente,
 * estos días **reducen la acumulación de vacaciones** del mes en que ocurrieron,
 * así que cargar este histórico puede dejar el saldo por debajo del que la
 * empresa informó en la plantilla de empleados.
 */
class PlantillaDeLicencias extends PlantillaDeHistorico
{
    public function clave(): string
    {
        return 'licencias';
    }

    public function nombre(): string
    {
        return 'Histórico de licencias, ausencias y suspensiones';
    }

    public function hoja(): string
    {
        return 'Licencias y ausencias';
    }

    public function columnas(): array
    {
        return [
            $this->documento(),
            Columna::lista('Tipo de novedad', 'tipo', TipoDeLicencia::paraElegir()),
            Columna::fecha('Fecha inicial', 'fecha_inicio'),
            Columna::fecha('Fecha final', 'fecha_fin'),
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

    /**
     * Una sola tabla con su campo de clasificación, igual que el módulo, y sin
     * columna de días: los cuenta el motor en días corridos.
     *
     * La advertencia de la hoja de instrucciones —que estos días reducen la
     * acumulación de vacaciones— no cambia el saldo que informó la empresa, por
     * lo mismo que en el histórico de vacaciones: la reducción vive dentro de la
     * acumulación, y la acumulación arranca en la fecha de corte. Lo que estas
     * filas sí hacen es dejar el histórico completo para las alertas y los
     * informes.
     */
    public function escribir(array $valores, Carbon $fechaDeLaCarga): void
    {
        $this->empleado($valores)->licencias()->create([
            'tipo' => $valores['tipo'],
            'fecha_inicio' => $valores['fecha_inicio'],
            'fecha_fin' => $valores['fecha_fin'],
        ]);
    }

    private function formulario(FilaTraducida $fila): RegistrarLicencia
    {
        return RegistrarLicencia::create('/', 'POST', $fila->valores());
    }
}
