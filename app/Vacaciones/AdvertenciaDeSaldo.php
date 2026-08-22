<?php

namespace App\Vacaciones;

use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * La advertencia de saldo insuficiente (documento 1, 6.4, y documento 3, 5.5).
 *
 * Es la franja permanente del formulario de vacaciones: el saldo de hoy y, en
 * cuanto hay un periodo con sentido digitado, el saldo proyectado a la fecha
 * de disfrute —no al de hoy, que es la regla del documento 1—. Si los días
 * solicitados superan lo proyectado, se agrega el texto ámbar exacto del
 * documento 3. **No bloquea**: el mensaje solo informa, y el registro lo deja
 * pasar `RegistrarVacacion` igual que si no existiera esta clase.
 *
 * `SaldoDeVacaciones::faltantePara()` ya hace la única cuenta que hace falta,
 * desde F2-02. Aquí no se cuenta ni un día: se pide el número y se escribe la
 * frase.
 */
final class AdvertenciaDeSaldo
{
    /**
     * @return array{saldoActual: string, saldoProyectado: string|null, advertencia: string|null}
     */
    public static function paraLaPantalla(Vinculacion $vinculacion, ?int $dias, ?Carbon $fechaDeDisfrute): array
    {
        $saldo = SaldoDeVacaciones::de($vinculacion);
        $saldoActual = $saldo->al(Carbon::today());

        if ($dias === null || ! $fechaDeDisfrute instanceof Carbon) {
            return [
                'saldoActual' => Formato::diasDecimales($saldoActual),
                'saldoProyectado' => null,
                'advertencia' => null,
            ];
        }

        $saldoProyectado = $saldo->al($fechaDeDisfrute);
        $faltante = $saldo->faltantePara((float) $dias, $fechaDeDisfrute);

        return [
            'saldoActual' => Formato::diasDecimales($saldoActual),
            'saldoProyectado' => Formato::diasDecimales($saldoProyectado),
            'advertencia' => $faltante > 0.0
                ? self::mensaje($fechaDeDisfrute, $saldoProyectado, $dias, $faltante)
                : null,
        ];
    }

    /**
     * El texto exacto del documento 3, 5.5: «Al 28 de agosto tendrá 12,75 días
     * disponibles. Estás registrando 14 días: faltan 1,25 días.»
     */
    private static function mensaje(Carbon $fechaDeDisfrute, float $saldoProyectado, int $dias, float $faltante): string
    {
        return 'Al '.Formato::diaYMes($fechaDeDisfrute).' tendrá '.Formato::diasDecimales($saldoProyectado)
            .' disponibles. Estás registrando '.Formato::dias($dias).': faltan '.Formato::diasDecimales($faltante).'.';
    }
}
