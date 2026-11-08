<?php

namespace App\Inicio;

use App\Dominio\Calculo\PeriodoDePrueba;
use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * Los dos motivos de atención del panel de inicio que llevan al listado de
 * empleados (documento 3, sección 5.2).
 *
 * ## Por qué existe este enumerado y no dos filtros más en la barra
 *
 * La tabla 11.3 del documento 1 fija los filtros que **la barra ofrece** en cada
 * módulo, y empleados los tiene los cinco desde F4-01. Estos dos no son de esa
 * clase: nadie va a escoger «periodos de prueba que terminan esta semana» de una
 * lista desplegable. Son el **destino** de las tarjetas de acción, que la sección
 * 5.2 exige que sean «un enlace directo al listado filtrado», y por eso se leen
 * de la dirección, se validan contra estos dos casos y salen como etiqueta
 * removible —quien llega desde la tarjeta puede quitarlos y ver la planta
 * entera—, pero no aparecen como control en la barra de filtros.
 *
 * ## Los dos se resuelven contando, no en SQL
 *
 * Ninguno de los dos es una columna. El final del periodo de prueba depende de
 * la modalidad y del plazo pactado, y el saldo de vacaciones **se calcula, no se
 * guarda** (regla 2 del proyecto). Así que los dos preguntan al motor empleado
 * por empleado y devuelven identificadores, que es lo que el listado acota con
 * un `whereIn`. Es el mismo camino que toman las reglas de alerta, por el mismo
 * motivo.
 *
 * La caché de saldos no participa y no es un olvido: `CacheDeSaldos` no ofrece
 * el saldo de una persona por ninguna parte, justamente para que no pueda
 * volverse la fuente de verdad de una decisión sobre alguien (F4-02).
 */
enum AtencionDelInicio: string
{
    /**
     * Cuánto hacia atrás se buscan ingresos para el periodo de prueba. El más
     * largo son dos meses, así que tres bastan y sobran; es el mismo margen
     * holgado, y por el mismo motivo, que el de `ReglaDePeriodoDePrueba`.
     */
    private const MESES_DE_LA_VENTANA = 3;

    case PeriodosDePrueba = 'periodos-de-prueba';

    case VacacionesAcumuladas = 'vacaciones-acumuladas';

    /**
     * El texto de la etiqueta removible del listado.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::PeriodosDePrueba => 'Periodos de prueba que terminan esta semana',
            self::VacacionesAcumuladas => 'Con '.Formato::diasDecimales(
                SaldoDeVacaciones::diasDeUnPeriodoCompleto()
            ).' o más acumulados',
        };
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $atencion): string => $atencion->value, self::cases());
    }

    /**
     * Los empleados a los que les toca este motivo hoy.
     *
     * Devuelve identificadores y no una consulta porque la decisión no se puede
     * escribir en SQL: la toma el motor de cálculo, uno por uno.
     *
     * @return list<int>
     */
    public function identificadores(?Carbon $hoy = null): array
    {
        $hoy = ($hoy ?? Carbon::today())->copy()->startOfDay();

        return match ($this) {
            self::PeriodosDePrueba => $this->conPeriodoDePruebaQueTerminaEstaSemana($hoy),
            self::VacacionesAcumuladas => $this->conUnPeriodoCompletoAcumulado($hoy),
        };
    }

    /**
     * Cuántos empleados le tocan, que es la cifra de la tarjeta.
     */
    public function cuantos(?Carbon $hoy = null): int
    {
        return count($this->identificadores($hoy));
    }

    /**
     * Los que terminan su periodo de prueba entre hoy y el domingo.
     *
     * «Esta semana» es de hoy en adelante: un periodo de prueba que terminó
     * anteayer ya no exige nada que se pueda hacer a tiempo, y ponerlo en una
     * tarjeta de acción sería pedir algo imposible.
     *
     * **La obra o labor entra solo si tiene fecha estimada** (`OL-01`): es la
     * única forma en que tiene periodo de prueba. La que no la tiene la salta
     * `PeriodoDePrueba::aplica()`, igual que al fijo de menos de cinco días.
     *
     * @return list<int>
     */
    private function conPeriodoDePruebaQueTerminaEstaSemana(Carbon $hoy): array
    {
        // El domingo de esta semana, explícito: el primer día de la semana de
        // Carbon depende de la configuración regional, y en Colombia la semana
        // laboral termina el domingo.
        $hasta = $hoy->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();

        /*
         * La ventana de ingresos, igual que en `ReglaDePeriodoDePrueba` y por el
         * mismo motivo: el día en que termina el periodo de prueba no se puede
         * escribir en SQL —depende de la modalidad y del plazo pactado—, pero
         * nadie que ingresó hace más de tres meses puede tenerlo terminando
         * ahora, porque el más largo son dos. **La ventana es una optimización;
         * quien decide es la comparación de abajo**, y por eso sobra margen.
         */
        $candidatas = Vinculacion::query()
            ->activas()
            ->with('periodos')
            ->whereDate('fecha_ingreso', '<=', $hasta)
            ->whereDate('fecha_ingreso', '>=', $hoy->copy()->subMonths(self::MESES_DE_LA_VENTANA))
            ->get();

        $identificadores = [];

        foreach ($candidatas as $vinculacion) {
            $prueba = PeriodoDePrueba::de($vinculacion);

            // Sin periodo de prueba que calcular —un fijo de menos de cinco
            // días, una obra o labor sin fecha estimada—, igual que en
            // `ReglaDePeriodoDePrueba`.
            if (! $prueba->aplica()) {
                continue;
            }

            $termina = Carbon::parse($prueba->terminaEl()->toDateString());

            if ($termina->betweenIncluded($hoy, $hasta)) {
                $identificadores[] = (int) $vinculacion->getKey();
            }
        }

        return $identificadores;
    }

    /**
     * Los que hoy tienen acumulado un periodo completo de vacaciones o más.
     *
     * Es el «personas con acumulación excesiva» del capítulo 12 y el «personas
     * con más de 15 días acumulados» del prototipo. Los quince no están escritos
     * aquí: los calcula el motor a partir de la constante del artículo 186.
     *
     * @return list<int>
     */
    private function conUnPeriodoCompletoAcumulado(Carbon $hoy): array
    {
        $periodo = SaldoDeVacaciones::diasDeUnPeriodoCompleto();

        $identificadores = [];

        foreach (Vinculacion::query()->activas()->get() as $vinculacion) {
            if (SaldoDeVacaciones::de($vinculacion)->al($hoy) >= $periodo) {
                $identificadores[] = (int) $vinculacion->getKey();
            }
        }

        return $identificadores;
    }
}
