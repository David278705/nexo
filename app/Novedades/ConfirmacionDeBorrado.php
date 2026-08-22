<?php

namespace App\Novedades;

use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Models\Incapacidad;
use App\Models\Licencia;
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
