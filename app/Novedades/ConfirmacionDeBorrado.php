<?php

namespace App\Novedades;

use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\Movimiento;
use App\Models\Terminacion;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Lo que se lee antes de eliminar una novedad (documento 3, 2.4 y 6.3).
 *
 * La regla del proyecto es que **nada se pierde en silencio**: una acción
 * destructiva no pregunta «¿está seguro?», explica la consecuencia concreta y
 * ya calculada. La plantilla del documento 3, 6.3 es literal:
 *
 * > Vas a eliminar {descripción del registro}. {Consecuencia concreta}. Esta
 * > acción queda registrada en la auditoría.
 *
 * Y el ejemplo del 2.4 fija cómo se escribe la consecuencia cuando toca el
 * saldo: «Se eliminarán 8 días de vacaciones y el saldo de Carlos Ramírez
 * pasará de 4,25 a 12,25 días».
 *
 * ## Por qué el texto se arma en el servidor
 *
 * Por lo mismo que el aviso de terminación y las frases del cambio a término
 * indefinido: el número que aparece en la frase sale del motor de cálculo, que
 * **no corre en el navegador jamás** (regla 2), y así la prueba que comprueba
 * el efecto lee exactamente la misma frase que ve el usuario.
 *
 * ## De cuánto a cuánto pasa el saldo
 *
 * Del saldo de hoy al que habría hoy si esa novedad no existiera, que es lo
 * que responde `SaldoDeVacaciones::sin()` sin escribir nada. La cláusula del
 * saldo solo aparece **si el saldo cambia**: unas vacaciones que empiezan el
 * mes que viene todavía no se han descontado, y una licencia no reduce nada si
 * la empresa no tiene activo `suspensiones_afectan_vacaciones`. Anunciar un
 * cambio que no va a ocurrir es peor que no anunciar ninguno.
 */
final class ConfirmacionDeBorrado
{
    /**
     * El cierre literal de la plantilla del documento 3, 6.3. Es la
     * contrapartida de dar libertad total sobre los registros (documento 1,
     * 6.2) y por eso se dice siempre, en las tres novedades.
     */
    public const AUDITORIA = 'Esta acción queda registrada en la auditoría.';

    /**
     * Lo que se lee en un movimiento que no es el más reciente del empleado
     * (REV-03): eliminarlo dejaría el histórico sin un «antes» claro al que
     * volver, porque el de encima guardó como anteriores los valores de este.
     *
     * Vive aquí, con las demás frases del borrado, porque es la misma
     * conversación: la pantalla la enseña en la fila y el servidor la devuelve
     * si alguien manda la petición de todas formas.
     */
    public const HAY_UNO_POSTERIOR = 'Este empleado tiene un movimiento posterior. '
        .'Eliminar un movimiento devuelve el cargo y el salario que había justo antes, '
        .'así que primero hay que eliminar el más reciente.';

    public static function deIncapacidad(Incapacidad $incapacidad): string
    {
        /*
         * Sin consecuencia calculada: una incapacidad no toca el saldo de
         * vacaciones ni ningún otro número del sistema. El ejemplo del
         * documento 3, 2.5 es exactamente este y tampoco la lleva.
         */
        return self::frase(
            'la incapacidad '.Formato::periodo($incapacidad->fecha_inicio, $incapacidad->fecha_fin)
                .' de '.self::nombre($incapacidad->vinculacion),
            null,
        );
    }

    public static function deVacacion(Vacacion $vacacion): string
    {
        $vinculacion = $vacacion->vinculacion;

        return self::frase(
            'las vacaciones '.Formato::periodo($vacacion->fecha_inicio, $vacacion->fecha_fin)
                .' de '.self::nombre($vinculacion),
            'Se eliminarán '.Formato::dias($vacacion->dias()).' de vacaciones'
                .self::efectoSobreElSaldo($vinculacion, $vacacion),
        );
    }

    public static function deLicencia(Licencia $licencia): string
    {
        $vinculacion = $licencia->vinculacion;

        return self::frase(
            'la '.Str::lower($licencia->tipo->etiqueta()).' '
                .Formato::periodo($licencia->fecha_inicio, $licencia->fecha_fin)
                .' de '.self::nombre($vinculacion),
            'Se eliminarán '.Formato::dias($licencia->dias()).' de suspensión del contrato'
                .self::efectoSobreElSaldo($vinculacion, $licencia),
        );
    }

    /**
     * Un aumento o una promoción (REV-03).
     *
     * Aquí la consecuencia no es un número del motor sino un estado que vuelve
     * atrás, y se dice con los mismos valores que se van a escribir: los que el
     * propio movimiento guardó como anteriores. La frase y el efecto no pueden
     * discrepar porque leen la misma fila.
     */
    public static function deMovimiento(Movimiento $movimiento): string
    {
        $nombre = self::nombre($movimiento->vinculacion);
        $salario = Formato::dinero($movimiento->getAttribute('salario_anterior'));

        // Se pregunta a los dos cargos y no al tipo: una promoción registrada
        // sin cargo nuevo deja el mismo, y anunciar que «volverá a» el que ya
        // tiene sería anunciar un cambio que no ocurre, igual que la cláusula
        // del saldo de las vacaciones.
        $consecuencia = $movimiento->cargo_anterior !== $movimiento->cargo_nuevo
            ? 'El cargo de '.$nombre.' volverá a '.$movimiento->cargo_anterior
                .' y su salario a '.$salario
            : 'El salario de '.$nombre.' volverá a '.$salario;

        return self::frase(
            $movimiento->tipo->conArticulo().' del '.Formato::fechaLarga($movimiento->fecha_efectividad)
                .' de '.$nombre,
            $consecuencia,
        );
    }

    /**
     * Una salida registrada (REV-03).
     *
     * La descripción dice «la terminación» y no el tipo de salida porque los
     * siete tipos no comparten género —«el despido con justa causa», «la
     * renuncia»— y el que corresponde ya está en la fila que se acaba de pulsar.
     *
     * La segunda mitad de la consecuencia es la que de verdad importa y por eso
     * se dice: quien vuelve a estar activo vuelve a entrar en el motor de
     * alertas, que es la promesa que vende el producto.
     */
    public static function deTerminacion(Terminacion $terminacion): string
    {
        $nombre = self::nombre($terminacion->vinculacion);

        return self::frase(
            'la terminación del '.Formato::fechaLarga($terminacion->fecha_salida).' de '.$nombre,
            $nombre.' volverá a estar activo y a recibir alertas',
        );
    }

    /**
     * La plantilla del documento 3, 6.3, con la consecuencia en medio cuando la
     * novedad tiene alguna que contar.
     */
    private static function frase(string $descripcion, ?string $consecuencia): string
    {
        $consecuencia = $consecuencia === null ? '' : $consecuencia.'. ';

        return 'Vas a eliminar '.$descripcion.'. '.$consecuencia.self::AUDITORIA;
    }

    /**
     * La segunda mitad de la consecuencia: «y el saldo de Carlos Ramírez pasará
     * de 4,25 a 12,25 días», o nada si eliminar la novedad no mueve el saldo.
     */
    private static function efectoSobreElSaldo(Vinculacion $vinculacion, Vacacion|Licencia $novedad): string
    {
        $hoy = Carbon::today();

        $saldo = SaldoDeVacaciones::de($vinculacion);
        $antes = $saldo->al($hoy);
        $despues = $saldo->sin($novedad)->al($hoy);

        if ($antes === $despues) {
            return '';
        }

        return ' y el saldo de '.self::nombre($vinculacion)
            .' pasará de '.Formato::numeroDecimal($antes)
            .' a '.Formato::diasDecimales($despues);
    }

    private static function nombre(Vinculacion $vinculacion): string
    {
        return $vinculacion->persona->nombreCompleto();
    }
}
