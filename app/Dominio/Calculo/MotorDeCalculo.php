<?php

namespace App\Dominio\Calculo;

use App\Models\Festivo;
use App\Models\Licencia;
use App\Models\Tenant;
use App\Models\Vinculacion;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * El motor de cálculo del sistema (documento 2, capítulo 4).
 *
 * Es el corazón del producto: un error aquí no rompe una pantalla, produce
 * números equivocados en todos los clientes a la vez y el cliente puede haber
 * liquidado a alguien con ese número. Por eso la prueba va primero —los diez
 * casos obligatorios de la sección 4.2 están en
 * `tests/Feature/MotorDeCalculoTest.php`, escritos por F2-01— y por eso ningún
 * conteo de días vive fuera de `app/Dominio/Calculo/`.
 *
 * Las tres reglas de conteo son **tres funciones con nombres distintos**, nunca
 * una con un parámetro que se pueda pasar mal. Confundirlas es el error más
 * probable del proyecto: un día corrido de más en unas vacaciones es plata mal
 * liquidada.
 *
 * Ningún número legal se escribe aquí: salen de `config/reglas_laborales.php`.
 *
 * **La base comercial de treinta días.** La acumulación de vacaciones no se
 * prorratea sobre la duración real del mes sino sobre treinta días. No es una
 * regla inventada aquí: la fijan dos de los diez casos obligatorios leídos
 * juntos —el de los cuatro días de licencia, que pide «proporcional a 26 días,
 * no a 30», y el de la licencia del 16 de marzo al 15 de abril, que pide media
 * acumulación en los dos meses—. Está anotada en `diasComercialesDelMes()`.
 */
class MotorDeCalculo
{
    /**
     * Los días que tiene un mes para la acumulación de vacaciones.
     *
     * Es la base comercial del párrafo de arriba, no la duración real del mes.
     * Vive aquí como constante y no como un treinta suelto dentro de las
     * cuentas porque aparece en tres de ellas.
     */
    public const DIAS_DEL_MES_COMERCIAL = 30;

    /**
     * Días hábiles de vacaciones entre dos fechas, ambas incluidas.
     *
     * Excluye domingos y festivos siempre. Excluye sábados solo si la empresa no
     * los tiene como laborales, que es el parámetro `sabado_laboral`. Es la única
     * de las tres reglas que depende de la empresa, y por eso la recibe.
     *
     * Un festivo que cae en sábado no cuenta ni siquiera con el sábado laboral:
     * ese día no se trabaja en ninguna empresa, y es uno de los diez casos.
     *
     * Se usa en: vacaciones.
     */
    public static function diasHabilesVacaciones(CarbonInterface $inicio, CarbonInterface $fin, Tenant $empresa): int
    {
        $desde = self::dia($inicio);
        $hasta = self::dia($fin);

        if ($hasta->lessThan($desde)) {
            return 0;
        }

        $festivos = self::festivosEntre($desde, $hasta);
        $sabadoLaboral = (bool) $empresa->getAttribute('sabado_laboral');
        $habiles = 0;

        for ($dia = $desde->copy(); $dia->lessThanOrEqualTo($hasta); $dia->addDay()) {
            if ($dia->isSunday()) {
                continue;
            }

            if ($dia->isSaturday() && ! $sabadoLaboral) {
                continue;
            }

            if (in_array($dia->toDateString(), $festivos, true)) {
                continue;
            }

            $habiles++;
        }

        return $habiles;
    }

    /**
     * Días corridos entre dos fechas, contando el primero y el último.
     *
     * Todos los días: sábados, domingos y festivos incluidos. Del 14 al 16 de
     * julio son tres días, no dos.
     *
     * Se usa en: incapacidades, licencias, ausencias y suspensiones.
     */
    public static function diasCorridos(CarbonInterface $inicio, CarbonInterface $fin): int
    {
        $desde = self::dia($inicio);
        $hasta = self::dia($fin);

        if ($hasta->lessThan($desde)) {
            return 0;
        }

        return (int) $desde->diffInDays($hasta) + 1;
    }

    /**
     * Los días de vacaciones que causa una vinculación en un mes calendario.
     *
     * Son 1,25 días (artículo 186 del CST) reducidos por dos cosas: los días del
     * mes en que la persona todavía no estaba vinculada o ya se había ido, y los
     * días de suspensión de ese mes si la empresa tiene activo el parámetro
     * `suspensiones_afectan_vacaciones`. Las dos reducciones son la misma
     * aritmética —días que no acumulan sobre una base de treinta— y por eso se
     * suman antes de una única llamada a `acumulacionProporcional()`.
     *
     * `$licenciaExcluida` deja fuera una licencia concreta sin tocar la base de
     * datos. Es lo que permite responder «cómo quedaría el saldo si esta
     * novedad se eliminara» **antes** de eliminarla, que es lo que la
     * confirmación de borrado tiene que decir (documento 3, 2.4). Sin él, la
     * única forma de saberlo sería borrar primero y contar después.
     *
     * Se usa en: saldo de vacaciones.
     */
    public static function acumulacionMes(Vinculacion $vinculacion, CarbonInterface $mes, ?int $licenciaExcluida = null): float
    {
        $cubiertos = self::diasComercialesDelMes(
            $vinculacion->fecha_ingreso,
            $vinculacion->fechaDeSalida(),
            $mes,
        );

        $noAcumulan = self::DIAS_DEL_MES_COMERCIAL - $cubiertos
            + self::diasDeSuspensionDelMes($vinculacion, $mes, $licenciaExcluida);

        return self::acumulacionProporcional($noAcumulan);
    }

    /**
     * Los días de un mes que quedan cubiertos por una suspensión, en base
     * comercial de treinta días.
     *
     * Es exactamente `diasComercialesDelMes()` con las dos fechas obligatorias;
     * existe con nombre propio porque es como lo nombra el documento y porque
     * quien registra una licencia o una suspensión pregunta esto y no otra cosa.
     */
    public static function diasSuspendidosDelMes(CarbonInterface $inicio, CarbonInterface $fin, CarbonInterface $mes): int
    {
        return self::diasComercialesDelMes($inicio, $fin, $mes);
    }

    /**
     * Los días de un mes, en base comercial de treinta, que caen dentro de un
     * rango. Un `fin` nulo significa «y de ahí en adelante».
     *
     * **La base es 30 y no la duración real del mes.** Lo fija el caso
     * obligatorio de los cuatro días de licencia —«acumulación proporcional a 26
     * días, no a 30»— y lo confirma el de la licencia del 16 de marzo al 15 de
     * abril, que debe dar media acumulación en los dos meses: del 16 al 31 de
     * marzo son dieciséis días naturales y solo son quince si el día 31 no
     * cuenta.
     *
     * De ahí las dos reglas de la base comercial, que son la misma vista por sus
     * dos extremos: **el día 31 no cuenta**, y **un mes cubierto entero son
     * treinta días** aunque tenga veintiocho. Sin la segunda, una licencia de
     * todo febrero dejaría dos días acumulando sin que nadie trabajara.
     */
    public static function diasComercialesDelMes(CarbonInterface $inicio, ?CarbonInterface $fin, CarbonInterface $mes): int
    {
        $primero = self::dia($mes)->startOfMonth();
        // Sin hora: `endOfMonth()` deja las 23:59:59 y ninguna fecha del motor
        // llega con hora, así que el último día nunca sería igual a sí mismo.
        $ultimo = $primero->copy()->endOfMonth()->startOfDay();

        $desde = self::dia($inicio)->max($primero);
        $hasta = $fin === null ? $ultimo : self::dia($fin)->min($ultimo);

        if ($hasta->lessThan($desde)) {
            return 0;
        }

        if ($desde->equalTo($primero) && $hasta->equalTo($ultimo)) {
            return self::DIAS_DEL_MES_COMERCIAL;
        }

        // Fuera del mes completo, el día 31 no existe para la base comercial.
        $hasta = $hasta->min($primero->copy()->addDays(self::DIAS_DEL_MES_COMERCIAL - 1));

        return self::diasCorridos($desde, $hasta);
    }

    /**
     * La acumulación de un mes con esos días sin acumular: 1,25 × (30 − días) / 30.
     *
     * Está separada de `acumulacionMes()` porque es la aritmética pura y es lo
     * que miden dos de los diez casos obligatorios. `acumulacionMes()` es quien
     * sabe leer la vinculación y sus suspensiones, y termina llamando aquí.
     */
    public static function acumulacionProporcional(int $diasSuspendidos): float
    {
        $acumulan = self::DIAS_DEL_MES_COMERCIAL - max(0, min(self::DIAS_DEL_MES_COMERCIAL, $diasSuspendidos));

        $porMes = (float) config('reglas_laborales.dias_vacaciones_por_mes');

        return $porMes * $acumulan / self::DIAS_DEL_MES_COMERCIAL;
    }

    /**
     * El día en que una fecha cumple un número de meses.
     *
     * Quien ingresa el 15 de abril cumple un mes el 14 de mayo, no el 15: el día
     * de ingreso es el primer día trabajado y el mes se cumple la víspera del
     * mismo día del mes siguiente. De aquí salen las alertas de periodo de prueba
     * del capítulo 9.
     */
    public static function cumpleMesesEl(CarbonInterface $ingreso, int $meses): CarbonInterface
    {
        return self::dia($ingreso)->addMonthsNoOverflow($meses)->subDay();
    }

    /**
     * El día en que se envía una alerta programada para esa fecha.
     *
     * Si cae en sábado, domingo o festivo se corre al día hábil anterior
     * (documento 1, 9.1); si es un día de oficina se envía ese mismo día.
     * **Aquí el sábado nunca es hábil**, tenga la empresa el sábado laboral o
     * no: el correo lo lee una persona de gestión humana un día de oficina. Por
     * eso el nombre dice «para alertas» y no se puede confundir con el conteo de
     * vacaciones, que sí mira el parámetro de la empresa.
     */
    public static function diaHabilAnteriorParaAlertas(CarbonInterface $fecha): CarbonInterface
    {
        $dia = self::dia($fecha);

        while ($dia->isSaturday() || $dia->isSunday() || Festivo::esFestivo($dia)) {
            $dia->subDay();
        }

        return $dia;
    }

    /**
     * Cuántos días faltan desde el envío de una alerta hasta el hecho que avisa.
     *
     * Es la diferencia entre dos fechas y **no** los días corridos: del viernes
     * al domingo faltan dos días, aunque sean tres días corridos. Es el número
     * que se escribe en el texto del correo —«en dos días (fecha)»— y por eso
     * tiene función propia: escribirlo con la regla equivocada haría que un
     * correo correcto dijera algo falso.
     */
    public static function diasDeAnticipacion(CarbonInterface $envio, CarbonInterface $hecho): int
    {
        return (int) self::dia($envio)->diffInDays(self::dia($hecho));
    }

    /**
     * Los días de suspensión de un mes que reducen la acumulación.
     *
     * Suma, para cada licencia, ausencia o suspensión de la vinculación, los
     * días de ese mes con `diasSuspendidosDelMes()`, y solo si la empresa
     * tiene activo `suspensiones_afectan_vacaciones` —el parámetro se lee aquí
     * y no en quien llama, para que ninguna pantalla pueda olvidarse de
     * mirarlo—. Sin el parámetro activo, ninguna licencia reduce nada.
     */
    private static function diasDeSuspensionDelMes(Vinculacion $vinculacion, CarbonInterface $mes, ?int $licenciaExcluida = null): int
    {
        if ($vinculacion->tenant?->getAttribute('suspensiones_afectan_vacaciones') !== true) {
            return 0;
        }

        $licencias = $vinculacion->licencias();

        if ($licenciaExcluida !== null) {
            $licencias->whereKeyNot($licenciaExcluida);
        }

        return (int) $licencias
            ->get()
            ->sum(fn (Licencia $licencia): int => self::diasSuspendidosDelMes(
                $licencia->fecha_inicio,
                $licencia->fecha_fin,
                $mes,
            ));
    }

    /**
     * Las fechas festivas del rango, como cadenas `Y-m-d`.
     *
     * Se leen por año y de una sola vez: preguntar por cada día del rango serían
     * quince consultas para unas vacaciones de quince días.
     *
     * @return list<string>
     */
    private static function festivosEntre(Carbon $desde, Carbon $hasta): array
    {
        $festivos = [];

        for ($anio = $desde->year; $anio <= $hasta->year; $anio++) {
            $festivos = [...$festivos, ...Festivo::fechasDelAnio($anio)];
        }

        return $festivos;
    }

    /**
     * La fecha sin hora y como objeto propio.
     *
     * Todo lo que entra al motor pasa por aquí, por dos motivos. El primero es
     * el error clásico de zona horaria: un servidor en UTC interpreta el 1 de
     * agosto como el 31 de julio a las 19:00 y el conteo sale con un día menos.
     * El segundo es que las fechas llegan como `CarbonInterface` —que puede ser
     * inmutable o no— y el motor las recorre con `addDay()`: sin la copia, un
     * `Carbon` mutable de quien llama se movería solo.
     */
    private static function dia(CarbonInterface $fecha): Carbon
    {
        return Carbon::parse($fecha->toDateString());
    }
}
