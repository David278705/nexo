<?php

namespace App\Dominio\Calculo;

use App\Models\SaldoEnCache;
use App\Models\Vinculacion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * La caché de saldos de vacaciones (documento 2, 3.4).
 *
 * Existe por un problema concreto: el saldo es una función y no una columna
 * —regla 2 del proyecto—, así que sumar el de toda la planta significa recorrer
 * mes a mes a cada empleado contra la base de datos. Para una ficha eso no se
 * nota; para el panel de inicio de una empresa de doscientas personas, sí.
 *
 * ## La caché nunca es la fuente de verdad
 *
 * Es la regla explícita de la sección 3.4 y del motor: **si hay diferencia,
 * manda el cálculo**. Aquí se cumple no por disciplina sino por construcción:
 *
 * - **Nadie pregunta por el saldo de una persona a esta clase.** No hay ningún
 *   método que lo devuelva. La ficha del empleado, la advertencia de saldo
 *   insuficiente, la confirmación de borrado y los dos resúmenes mensuales de
 *   vacaciones siguen llamando a `SaldoDeVacaciones`, que calcula.
 * - **`total()` es lo único que lee la tabla**, y solo para sumar.
 * - **`refrescar()` es lo único que la escribe**, y lo hace copiando el
 *   resultado de `SaldoDeVacaciones::al()`. No hay una segunda aritmética del
 *   saldo que pueda separarse de la primera.
 *
 * De ahí que borrar la tabla entera no pierda un solo dato: la noche siguiente
 * vuelve a estar igual, y mientras tanto `total()` responde lo mismo, más
 * despacio.
 *
 * ## Quién la refresca
 *
 * El proceso diario de alertas, empresa por empresa y ya dentro del contexto de
 * cada una, que es lo que pide la sección 3.4: «refrescada cada noche por el
 * mismo proceso que envía las alertas». Ninguno de los dos métodos filtra por
 * empresa a mano: el global scope de `PerteneceATenant` lo hace en las dos
 * tablas que se tocan.
 */
class CacheDeSaldos
{
    /**
     * Cuántos empleados por vuelta al recalcular. No es un umbral de negocio:
     * es cuánta plantilla cabe en memoria de una vez sin que deje de ser cierto
     * que el proceso puede con una empresa grande.
     */
    private const TAMANO_DEL_LOTE = 200;

    /**
     * Vuelve a calcular el saldo de todos los empleados activos de la empresa
     * en contexto y deja la caché diciendo exactamente eso.
     *
     * Devuelve cuántas filas quedaron, que es cuántos empleados activos hay.
     *
     * Son tres cosas y en este orden:
     *
     * 1. Cada activo se recalcula y se escribe. `updateOrCreate` sobre el
     *    índice único es lo que hace que refrescar dos veces la misma noche
     *    —o dos veces seguidas a mano— no duplique a nadie.
     * 2. Los que ya no están activos pierden su fila. Un empleado terminado
     *    deja de contar en el total desde el refresco siguiente, igual que deja
     *    de recibir alertas.
     * 3. Se cuenta lo que quedó.
     *
     * Se recorre por lotes: es el único punto del sistema que carga a la
     * plantilla entera, y cargarla de una vez en memoria es justo lo que esta
     * clase existe para que nadie tenga que hacer.
     */
    public static function refrescar(?CarbonInterface $fecha = null): int
    {
        $al = Carbon::parse(($fecha ?? Carbon::today())->toDateString());
        $ahora = Carbon::now();

        Vinculacion::query()
            ->activas()
            ->orderBy('id')
            ->chunkById(self::TAMANO_DEL_LOTE, function ($lote) use ($al, $ahora): void {
                foreach ($lote as $vinculacion) {
                    SaldoEnCache::updateOrCreate(
                        ['vinculacion_id' => $vinculacion->getKey()],
                        [
                            'dias' => SaldoDeVacaciones::de($vinculacion)->al($al),
                            'calculado_al' => $al,
                            'calculado_en' => $ahora,
                        ],
                    );
                }
            });

        SaldoEnCache::query()
            ->whereNotIn('vinculacion_id', self::activas()->select('id'))
            ->delete();

        return SaldoEnCache::query()->count();
    }

    /**
     * El saldo acumulado de toda la planta de la empresa en contexto.
     *
     * En régimen son tres consultas y ninguna cuenta laboral: la caché ya trae
     * el número de cada uno. Los empleados registrados después del último
     * refresco todavía no tienen fila, y esos sí se calculan aquí mismo, uno a
     * uno. **No se dejan fuera**: un total que se olvidara de los ingresos de
     * ayer sería un número equivocado, y la caché puede dar un número viejo pero
     * no uno falso. Tampoco se escriben: escribir en medio de una petición de
     * lectura convertiría un panel en un proceso de mantenimiento.
     *
     * La fecha del total es la de la caché, no la de hoy, y viaja dentro del
     * resultado para que la pantalla la diga. Los que se calculan al vuelo se
     * calculan **a esa misma fecha**, de modo que la cifra es coherente: un
     * total mezclado de dos fechas no sería el saldo de ningún día.
     */
    public static function total(): TotalDeSaldos
    {
        $al = self::fechaDeLaCache() ?? Carbon::today();

        $enCache = SaldoEnCache::query()
            ->whereIn('vinculacion_id', self::activas()->select('id'))
            ->get(['vinculacion_id', 'dias']);

        $dias = (float) $enCache->sum('dias');
        $alVuelo = 0;

        $sinFila = self::activas()
            ->whereNotIn('id', SaldoEnCache::query()->select('vinculacion_id'))
            ->get();

        foreach ($sinFila as $vinculacion) {
            $dias += SaldoDeVacaciones::de($vinculacion)->al($al);
            $alVuelo++;
        }

        return new TotalDeSaldos(
            dias: round($dias, 4),
            empleados: $enCache->count() + $alVuelo,
            desdeLaCache: $enCache->count(),
            calculadosAlVuelo: $alVuelo,
            al: $al,
        );
    }

    /**
     * La fecha a la que se refiere lo que hay guardado, o nulo si no hay nada.
     *
     * Un refresco deja a toda la empresa en la misma fecha, así que preguntar
     * por la mayor es preguntar por la del último que corrió.
     */
    private static function fechaDeLaCache(): ?Carbon
    {
        /** @var string|null $fecha */
        $fecha = SaldoEnCache::query()->max('calculado_al');

        return $fecha === null ? null : Carbon::parse($fecha);
    }

    /**
     * Los empleados activos de la empresa en contexto.
     *
     * Se usa como subconsulta en los tres cruces con la caché en lugar de
     * traerse la lista de identificadores: con doscientos empleados daría lo
     * mismo, y con cinco mil no.
     *
     * @return Builder<Vinculacion>
     */
    private static function activas(): Builder
    {
        return Vinculacion::query()->activas();
    }
}
