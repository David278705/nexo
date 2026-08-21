<?php

namespace App\Dominio\Calculo;

use App\Models\Tenant;
use App\Models\Vinculacion;
use Carbon\CarbonInterface;

/**
 * El motor de cálculo del sistema (documento 2, capítulo 4).
 *
 * **Aquí todavía no hay cálculo: hay firmas.** Esta clase la abre F2-01, cuya
 * única tarea es escribir los diez casos obligatorios de la sección 4.2 y verlos
 * fallar. Cada método lanza `CalculoSinImplementar`, de modo que los diez fallan
 * por falta de implementación —que es lo que la tarea pide demostrar— y no por
 * un error de sintaxis ni por una clase que no existe. F2-02 sustituye cada
 * `throw` por su cuenta y los diez pasan.
 *
 * Las tres reglas de conteo son **tres funciones con nombres distintos**, nunca
 * una con un parámetro que se pueda pasar mal. Confundirlas es el error más
 * probable del proyecto: un día corrido de más en unas vacaciones es plata mal
 * liquidada en todos los clientes a la vez.
 *
 * Ningún número legal se escribe aquí: salen de `config/reglas_laborales.php`.
 */
class MotorDeCalculo
{
    /**
     * Días hábiles de vacaciones entre dos fechas, ambas incluidas.
     *
     * Excluye domingos y festivos siempre. Excluye sábados solo si la empresa no
     * los tiene como laborales, que es el parámetro `sabado_laboral`. Es la única
     * de las tres reglas que depende de la empresa, y por eso la recibe.
     *
     * Se usa en: vacaciones.
     */
    public static function diasHabilesVacaciones(CarbonInterface $inicio, CarbonInterface $fin, Tenant $empresa): int
    {
        throw CalculoSinImplementar::en(__METHOD__);
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
        throw CalculoSinImplementar::en(__METHOD__);
    }

    /**
     * Los días de vacaciones que causa una vinculación en un mes calendario.
     *
     * Son 1,25 días (artículo 186 del CST), reducidos en proporción a los días
     * de suspensión de ese mes si la empresa tiene activo el parámetro
     * `suspensiones_afectan_vacaciones`. De dónde salen esos días de suspensión
     * es cosa de F2-02: aquí solo está la firma que fija el documento.
     *
     * Se usa en: saldo de vacaciones.
     */
    public static function acumulacionMes(Vinculacion $vinculacion, CarbonInterface $mes): float
    {
        throw CalculoSinImplementar::en(__METHOD__);
    }

    /**
     * Los días de un mes que quedan cubiertos por una suspensión, en base
     * comercial de treinta días.
     *
     * La base es 30 y no la duración real del mes: lo fija el caso obligatorio
     * de los cuatro días de licencia —«acumulación proporcional a 26 días, no a
     * 30»— y lo confirma el de la licencia del 16 de marzo al 15 de abril, que
     * debe dar media acumulación en los dos meses. Del 16 al 31 de marzo son
     * dieciséis días naturales; solo son quince si el día 31 no cuenta.
     */
    public static function diasSuspendidosDelMes(CarbonInterface $inicio, CarbonInterface $fin, CarbonInterface $mes): int
    {
        throw CalculoSinImplementar::en(__METHOD__);
    }

    /**
     * La acumulación de un mes con esos días suspendidos: 1,25 × (30 − días) / 30.
     *
     * Está separada de `acumulacionMes()` porque es la aritmética pura y es lo
     * que miden dos de los diez casos obligatorios. `acumulacionMes()` es quien
     * sabe leer las suspensiones de la vinculación y termina llamando aquí.
     */
    public static function acumulacionProporcional(int $diasSuspendidos): float
    {
        throw CalculoSinImplementar::en(__METHOD__);
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
        throw CalculoSinImplementar::en(__METHOD__);
    }

    /**
     * El día en que se envía una alerta programada para esa fecha.
     *
     * Si cae en sábado, domingo o festivo se corre al día hábil anterior
     * (documento 1, 9.1). **Aquí el sábado nunca es hábil**, tenga la empresa el
     * sábado laboral o no: el correo lo lee una persona de gestión humana un día
     * de oficina. Por eso el nombre dice «para alertas» y no se puede confundir
     * con el conteo de vacaciones, que sí mira el parámetro de la empresa.
     */
    public static function diaHabilAnteriorParaAlertas(CarbonInterface $fecha): CarbonInterface
    {
        throw CalculoSinImplementar::en(__METHOD__);
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
        throw CalculoSinImplementar::en(__METHOD__);
    }
}
