<?php

namespace App\Contratacion;

use App\Models\Persona;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * La advertencia de reingreso de la sección 5.6 del documento 1.
 *
 * Cada vinculación es un registro independiente: si una persona se retiró y
 * vuelve, se crea uno nuevo y no se reactiva el anterior. Cuando el documento
 * que se está digitando ya tuvo un registro en esa empresa, el sistema lo dice
 * en pantalla.
 *
 * Tres cosas que esta clase **no** hace, y las tres son la regla:
 *
 * - **No bloquea.** El reingreso es legítimo; lo que se advierte es que hay que
 *   revisar si el periodo de prueba se puede pactar otra vez.
 * - **No decide si el periodo de prueba aplica.** Es un asunto legal que depende
 *   de cada caso y le corresponde a la empresa.
 * - **No apaga ninguna alerta.** Las de periodo de prueba se generan igual, y la
 *   nota que acompaña al aviso lo dice con todas sus letras.
 *
 * Los dos textos son literales del documento y por eso son constantes: viven en
 * el servidor —y no en el componente Vue— para que una prueba pueda comprobar
 * que no se les cambió una coma.
 */
final class AdvertenciaDeReingreso
{
    /**
     * El texto exacto de la advertencia (documento 1, 5.6).
     */
    public const TEXTO = 'Esta persona ya tuvo una vinculación anterior con la empresa. Verifique si es válido pactar periodo de prueba en este contrato.';

    /**
     * La nota que va a continuación, también literal.
     */
    public const NOTA = 'Por configuración del sistema usted recibirá las alertas de periodo de prueba estipuladas. Sin embargo, le corresponde a usted verificar si ese periodo de prueba es válido, considerando que la persona ya tuvo una vinculación previa.';

    /**
     * La ventana que le da énfasis especial al aviso (documento 1, 5.6).
     */
    public const DIAS_DE_ENFASIS = 30;

    /**
     * Lo que hay que mostrar en pantalla, o `null` si no hay nada que advertir.
     *
     * `$fechaDeIngreso` llega vacía mientras se está en el primer paso del
     * asistente, donde todavía no se ha digitado: en ese caso el reingreso se
     * mide contra hoy, que es la mejor estimación disponible, y se recalcula
     * cuando la fecha de ingreso se escribe en el tercer paso.
     *
     * @return array<string, mixed>|null
     */
    public static function para(?string $numeroDeDocumento, ?string $fechaDeIngreso = null): ?array
    {
        $documento = trim((string) $numeroDeDocumento);

        if ($documento === '') {
            return null;
        }

        $persona = Persona::query()
            ->conDocumento($documento)
            ->with('vinculaciones')
            ->first();

        if (! $persona instanceof Persona || $persona->vinculaciones->isEmpty()) {
            return null;
        }

        $salida = self::ultimaSalida($persona);
        $dias = self::diasDesdeLaSalida($salida, $fechaDeIngreso);
        $reciente = $dias !== null && $dias >= 0 && $dias <= self::DIAS_DE_ENFASIS;

        return [
            'texto' => self::TEXTO,
            'nota' => self::NOTA,
            'nombre' => $persona->nombreCompleto(),
            'vinculaciones' => $persona->vinculaciones->count(),
            'reciente' => $reciente,
            'detalle' => self::detalle($salida, $dias, $reciente),
        ];
    }

    /**
     * La fecha de salida más reciente entre las vinculaciones anteriores.
     *
     * Puede ser nula aunque la persona tenga vinculaciones: una todavía activa
     * no tiene salida, y una inactiva puede no tener registrada la fecha
     * mientras `Vinculacion::fechaDeSalida()` siga siendo provisional. Sin ella
     * el aviso se muestra igual, solo que sin el énfasis de los 30 días.
     */
    private static function ultimaSalida(Persona $persona): ?Carbon
    {
        return $persona->vinculaciones
            ->map(fn (Vinculacion $vinculacion): ?Carbon => $vinculacion->fechaDeSalida())
            ->filter()
            ->sort()
            ->last();
    }

    /**
     * Cuántos días separan la salida anterior del ingreso que se está digitando.
     *
     * Negativo si el ingreso es anterior a la salida, que es un dato mal
     * digitado y no un reingreso cercano: por eso el énfasis exige que no lo sea.
     */
    private static function diasDesdeLaSalida(?Carbon $salida, ?string $fechaDeIngreso): ?int
    {
        if (! $salida instanceof Carbon) {
            return null;
        }

        $ingreso = self::fecha($fechaDeIngreso) ?? Carbon::today();

        return (int) $salida->diffInDays($ingreso, false);
    }

    private static function fecha(?string $fecha): ?Carbon
    {
        if ($fecha === null || trim($fecha) === '') {
            return null;
        }

        try {
            return Carbon::parse($fecha)->startOfDay();
        } catch (\Throwable) {
            // El asistente manda lo que haya en el campo, que puede ser una
            // fecha a medio escribir. Se mide contra hoy y ya.
            return null;
        }
    }

    /**
     * La línea que sitúa el reingreso en el tiempo.
     *
     * Toda referencia temporal lleva la fecha entre paréntesis, que es la regla
     * de interfaz del documento 3.
     */
    private static function detalle(?Carbon $salida, ?int $dias, bool $reciente): ?string
    {
        if (! $salida instanceof Carbon) {
            return null;
        }

        $fecha = Formato::fechaLarga($salida);

        if (! $reciente) {
            return "La vinculación anterior terminó el {$fecha}.";
        }

        if ($dias === 0) {
            return "El reingreso es el mismo día de la salida anterior ({$fecha}).";
        }

        $cuantos = Formato::dias((int) $dias);

        return "El reingreso es {$cuantos} después de la salida anterior ({$fecha}), dentro de los ".self::DIAS_DE_ENFASIS.' días siguientes.';
    }
}
