<?php

namespace App\CargaInicial\Plantillas;

use App\CargaInicial\Columna;
use App\CargaInicial\ErrorDeCarga;
use App\CargaInicial\FilaTraducida;
use App\CargaInicial\PlantaDeLaEmpresa;
use App\CargaInicial\TextoDeCelda;
use App\Http\Requests\RegistrarMovimiento;
use App\Movimientos\TipoDeMovimiento;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * La plantilla 6, «Histórico de aumentos y promociones» (documento 1, 8.1).
 *
 * Es la única que trae el valor anterior además del nuevo, por la misma razón
 * por la que la tabla `movimientos` lo guarda: el histórico enseña «el anterior
 * frente al nuevo», y cuando el movimiento se registra el valor vigente ya
 * cambió.
 *
 * **Su hoja de instrucciones promete una comprobación y aquí se cumple:** el
 * salario y el cargo del último movimiento de cada persona tienen que coincidir
 * con los que informó la plantilla de empleados. Es la única forma de detectar
 * las dos equivocaciones que este archivo hace fáciles —informar el salario
 * viejo en la planta, u olvidar el último aumento en el histórico—, y ninguna
 * de las dos se ve mirando una fila sola.
 */
class PlantillaDeMovimientos extends PlantillaDeHistorico
{
    public function clave(): string
    {
        return 'movimientos';
    }

    public function nombre(): string
    {
        return 'Histórico de aumentos y promociones';
    }

    public function hoja(): string
    {
        return 'Movimientos salariales';
    }

    public function columnas(): array
    {
        return [
            $this->documento(),
            Columna::lista('Tipo de movimiento', 'tipo', TipoDeMovimiento::paraElegir()),
            Columna::fecha('Fecha de efectividad', 'fecha_efectividad'),
            Columna::texto('Cargo anterior', 'cargo_anterior'),
            Columna::texto('Cargo nuevo', 'cargo_nuevo'),
            Columna::dinero('Salario anterior', 'salario_anterior'),
            Columna::dinero('Salario nuevo', 'salario_nuevo'),
        ];
    }

    public function reglas(FilaTraducida $fila): array
    {
        return [
            ...$this->reglaDelDocumento(),
            ...$this->formulario($fila)->rules(),

            // Las dos columnas que el formulario no pide porque las deduce del
            // empleado, y que en el archivo sí vienen escritas.
            'cargo_anterior' => ['required', 'string', 'max:255'],
            'salario_anterior' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    public function atributos(): array
    {
        return [
            'numero_documento' => 'número de documento',
            ...$this->formulario(new FilaTraducida(0))->attributes(),
            'cargo_anterior' => 'cargo anterior',
            'salario_anterior' => 'salario anterior',
        ];
    }

    public function mensajes(): array
    {
        return $this->formulario(new FilaTraducida(0))->messages();
    }

    public function revisarElConjunto(array $filas, PlantaDeLaEmpresa $planta): array
    {
        $errores = [];

        foreach ($this->ultimoDeCadaPersona($filas) as $documento => $fila) {
            $vinculacion = $planta->vinculacionActiva((string) $documento);

            if ($vinculacion === null) {
                continue;
            }

            $salario = $fila->valor('salario_nuevo');
            $cargo = $fila->valor('cargo_nuevo') ?? $fila->valor('cargo_anterior');

            $salarioDeLaPlanta = (float) $vinculacion->getAttribute('salario');
            $cargoDeLaPlanta = (string) $vinculacion->getAttribute('cargo');

            if (is_numeric($salario) && (float) $salario !== $salarioDeLaPlanta) {
                $errores[] = new ErrorDeCarga(
                    $fila->numero,
                    'Salario nuevo',
                    'Este es el último movimiento de la persona, así que su salario nuevo tendría que ser el mismo que informó la plantilla de empleados ('.
                        Formato::dinero($salarioDeLaPlanta).'), y es '.Formato::dinero((float) $salario).'.',
                );
            }

            if (is_string($cargo) && TextoDeCelda::comparable($cargo) !== TextoDeCelda::comparable($cargoDeLaPlanta)) {
                $errores[] = new ErrorDeCarga(
                    $fila->numero,
                    'Cargo nuevo',
                    "Este es el último movimiento de la persona, así que su cargo nuevo tendría que ser el mismo que informó la plantilla de empleados («{$cargoDeLaPlanta}»), y es «{$cargo}».",
                );
            }
        }

        return $errores;
    }

    /**
     * **El único `escribir()` que no pasa por el método del módulo**, y el
     * motivo es la diferencia entre registrar un movimiento y cargar el
     * histórico de los que ya ocurrieron.
     *
     * `Vinculacion::registrarMovimiento()` hace dos cosas a la vez: agrega la
     * fila y actualiza el cargo y el salario del empleado, tomando el «anterior»
     * de lo que rige hoy. Aquí lo de hoy ya es correcto —lo informó la plantilla
     * de empleados— y los anteriores vienen escritos en el archivo. Pasar por
     * ahí pondría como valor anterior del primer movimiento del pasado el
     * salario actual, que es exactamente al revés, y dejaría al empleado con el
     * salario del movimiento más viejo si el archivo no viene ordenado.
     *
     * La coherencia entre el último movimiento y la planta ya la comprobó
     * `revisarElConjunto()`, así que no actualizar nada aquí no deja ningún
     * desajuste: es la comprobación la que garantiza que no hace falta.
     */
    public function escribir(array $valores, Carbon $fechaDeLaCarga): void
    {
        $tipo = TipoDeMovimiento::from((string) $valores['tipo']);
        $cargoAnterior = (string) $valores['cargo_anterior'];
        $cargoNuevo = $valores['cargo_nuevo'] ?? null;

        $this->empleado($valores)->movimientos()->create([
            'tipo' => $tipo,
            'cargo_anterior' => $cargoAnterior,
            // La regla del cargo repetido en el aumento salarial es del módulo y
            // se le pregunta a él, no se copia aquí.
            'cargo_nuevo' => $tipo->cargoResultante($cargoAnterior, is_string($cargoNuevo) ? $cargoNuevo : null),
            'salario_anterior' => $valores['salario_anterior'],
            'salario_nuevo' => $valores['salario_nuevo'],
            'fecha_efectividad' => $valores['fecha_efectividad'],
        ]);
    }

    /**
     * El movimiento más reciente de cada documento, que es el único que tiene
     * que cuadrar con la planta: los anteriores describen el pasado.
     *
     * @param  list<FilaTraducida>  $filas
     * @return array<string, FilaTraducida>
     */
    private function ultimoDeCadaPersona(array $filas): array
    {
        $ultimos = [];

        foreach ($filas as $fila) {
            $documento = $fila->valor('numero_documento');
            $fecha = $fila->valor('fecha_efectividad');

            if ($fila->tieneErrores() || ! is_string($documento) || ! is_string($fecha)) {
                continue;
            }

            $anterior = $ultimos[$documento] ?? null;

            if ($anterior === null || $fecha >= (string) $anterior->valor('fecha_efectividad')) {
                $ultimos[$documento] = $fila;
            }
        }

        return $ultimos;
    }

    private function formulario(FilaTraducida $fila): RegistrarMovimiento
    {
        return RegistrarMovimiento::create('/', 'POST', $fila->valores());
    }
}
