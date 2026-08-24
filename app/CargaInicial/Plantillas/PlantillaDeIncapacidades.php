<?php

namespace App\CargaInicial\Plantillas;

use App\CargaInicial\Columna;
use App\CargaInicial\FilaTraducida;
use App\Http\Requests\RegistrarIncapacidad;
use App\Incapacidades\OrigenIncapacidad;
use App\Models\Eps;
use Illuminate\Support\Carbon;

/**
 * La plantilla 3, «Histórico de incapacidades» (documento 1, 6.3).
 *
 * No trae columna de días y no es un olvido: los días de una incapacidad se
 * cuentan en días corridos y los calcula el motor a partir de las dos fechas.
 * Un número escrito a mano en el archivo sería una segunda verdad, y la regla 3
 * del proyecto existe justamente para que no haya dos.
 *
 * **El código de diagnóstico se exige**, igual que en el formulario del módulo.
 * La hoja de instrucciones de la plantilla lo marca como opcional y la columna
 * de la base de datos no admite nulos, así que hoy manda el formulario; la
 * contradicción está anotada en `ESTADO.md` como pregunta para Angela.
 */
class PlantillaDeIncapacidades extends PlantillaDeHistorico
{
    public function clave(): string
    {
        return 'incapacidades';
    }

    public function nombre(): string
    {
        return 'Histórico de incapacidades';
    }

    public function hoja(): string
    {
        return 'Incapacidades';
    }

    public function columnas(): array
    {
        return [
            $this->documento(),
            Columna::fecha('Fecha de inicio', 'fecha_inicio'),
            Columna::fecha('Fecha de terminación', 'fecha_fin'),
            Columna::texto('Código de diagnóstico', 'codigo_diagnostico'),
            Columna::siNo('¿Es prórroga?', 'es_prorroga'),
            Columna::catalogo('EPS que la expide', 'eps_id', Eps::class),
            Columna::lista('Origen', 'origen', OrigenIncapacidad::paraElegir()),
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
     * Entra por la misma relación que usa el controlador del módulo y **sin
     * columna de días**: los cuenta el motor a partir de las dos fechas cada vez
     * que alguien los pide.
     *
     * El «sí/no» de la prórroga se traduce aquí igual que en el formulario, que
     * lo convierte con `RegistrarIncapacidad::esProrroga()`. La columna es
     * booleana: guardar ahí la cadena «no» la dejaría valiendo verdadero.
     */
    public function escribir(array $valores, Carbon $fechaDeLaCarga): void
    {
        $this->empleado($valores)->incapacidades()->create([
            'fecha_inicio' => $valores['fecha_inicio'],
            'fecha_fin' => $valores['fecha_fin'],
            'codigo_diagnostico' => $valores['codigo_diagnostico'],
            'es_prorroga' => $valores['es_prorroga'] === 'si',
            'eps_id' => $valores['eps_id'],
            'origen' => $valores['origen'],
        ]);
    }

    private function formulario(FilaTraducida $fila): RegistrarIncapacidad
    {
        return RegistrarIncapacidad::create('/', 'POST', $fila->valores());
    }
}
