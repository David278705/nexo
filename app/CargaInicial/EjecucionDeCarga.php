<?php

namespace App\CargaInicial;

use App\CargaInicial\Plantillas\PlantillaDeEmpleados;
use App\Models\RegistroDeCarga;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Los pasos 4, 5 y 6 del documento 2, 6.1: confirmar la carga, ejecutarla dentro
 * de una transacción y dejar constancia de ella.
 *
 * ## Vuelve a simular, siempre
 *
 * No recibe el informe que se le enseñó al proveedor: recibe el archivo y lo
 * vuelve a pasar entero por `SimulacionDeCarga`. Es deliberado y es lo que hace
 * que el informe no pueda mentir. Entre validar y confirmar pasan minutos, y en
 * esos minutos alguien pudo desactivar una EPS, o pudo subirse otro archivo, o
 * pudo cargarse la misma plantilla desde otra pestaña. Lo que se escribe es
 * exactamente lo que se acaba de validar, no lo que se validó antes.
 *
 * De paso, esto es lo que permite no guardar el Excel en ninguna parte
 * (documento 1, 13.1: «el Excel solo se usa para cargar, no se almacena ni se
 * conserva»): el archivo llega, se lee dos veces y se va.
 *
 * ## O entra todo o no entra nada
 *
 * Las filas y el registro de la carga se escriben dentro de una sola
 * transacción. Media planta cargada es peor que ninguna: nadie sabría dónde se
 * quedó, y volver a pasar el archivo duplicaría lo que sí entró.
 *
 * ## El orden entre plantillas
 *
 * Un histórico no entra si la plantilla de empleados no se ha procesado
 * (documento 2, 6.3). Se comprueba contra `cargas_iniciales` y no contra la
 * planta: una empresa puede tener empleados registrados por pantalla sin haber
 * pasado nunca por la carga, y entonces la comprobación diría que sí a algo que
 * nunca ocurrió.
 */
class EjecucionDeCarga
{
    public function __construct(private readonly SimulacionDeCarga $simulacion) {}

    /**
     * @param  string  $ruta  el archivo en disco, que no se conserva
     * @param  string  $archivo  el nombre con el que lo entregó el cliente
     *
     * @throws ArchivoIlegible
     * @throws CargaRechazada
     */
    public function ejecutar(
        Plantilla $plantilla,
        string $ruta,
        string $archivo,
        ?User $usuario = null,
    ): ResultadoDeLaCarga {
        $this->exigirLaPlanta($plantilla);

        $simulacion = $this->simulacion->ejecutar($plantilla, $ruta);

        if ($simulacion->hayErrores()) {
            throw CargaRechazada::porErrores($simulacion);
        }

        $fecha = Carbon::now()->startOfDay();

        return DB::transaction(function () use ($plantilla, $simulacion, $fecha, $archivo, $usuario): ResultadoDeLaCarga {
            $valores = $simulacion->valores();

            foreach ($valores as $fila) {
                $plantilla->escribir($fila, $fecha);
            }

            return new ResultadoDeLaCarga($simulacion, RegistroDeCarga::query()->create([
                'plantilla' => $plantilla->clave(),
                'archivo' => $archivo,
                'filas' => count($valores),
                'usuario_id' => $usuario?->getKey(),
                'fecha_corte_saldo' => $plantilla->fechaDeCorte($fecha),
            ]));
        });
    }

    /**
     * Si esta plantilla ya se cargó alguna vez en la empresa en contexto.
     *
     * Lo consulta la pantalla para avisar antes de que alguien vuelva a subir la
     * misma: cargar dos veces la plantilla de empleados no falla, abre una
     * segunda vinculación a cada persona, y eso no se nota hasta que el listado
     * enseña a todo el mundo repetido.
     */
    public function yaSeCargo(Plantilla $plantilla): bool
    {
        return RegistroDeCarga::query()->deLaPlantilla($plantilla->clave())->exists();
    }

    /**
     * @throws CargaRechazada
     */
    private function exigirLaPlanta(Plantilla $plantilla): void
    {
        if (! $plantilla->dependeDeLaPlanta()) {
            return;
        }

        if (! RegistroDeCarga::query()->deLaPlantilla(PlantillaDeEmpleados::CLAVE)->exists()) {
            throw CargaRechazada::sinPlantaCargada($plantilla);
        }
    }
}
