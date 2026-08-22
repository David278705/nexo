<?php

namespace App\Novedades;

use App\Dominio\Calculo\MotorDeCalculo;
use App\Presentacion\Formato;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Las dos fechas que el usuario está digitando en el modal de una novedad.
 *
 * Los tres módulos del capítulo 6 —incapacidades, vacaciones y licencias—
 * calculan en el servidor los días de lo que se está registrando y los mandan
 * por una recarga parcial, porque la regla 3 prohíbe restar fechas en el
 * navegador. Los tres tenían que leer antes las mismas dos fechas de la
 * dirección y decidir lo mismo: si no han llegado las dos, si alguna no es una
 * fecha, o si la de fin es anterior a la de inicio, todavía no hay nada que
 * calcular.
 *
 * Esa lectura estaba escrita tres veces, palabra por palabra, en
 * `IncapacidadController`, `LicenciaController` y `VacacionController`. Vive
 * aquí una vez, porque las tres copias tenían que decidir igual: un periodo que
 * un módulo acepta y otro descarta enseñaría dos números distintos para las
 * mismas fechas.
 */
class PeriodoDigitado
{
    /**
     * Las dos fechas convertidas y en orden, o nulo mientras no lleguen las dos
     * o no tengan sentido.
     *
     * Que falten o estén al revés no es un error que haya que reportar: el
     * formulario se está llenando y la validación de verdad la hace el
     * `FormRequest` al guardar. Aquí solo se decide si ya se puede contar.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public static function de(Request $peticion): ?array
    {
        $inicio = $peticion->query('fecha_inicio');
        $fin = $peticion->query('fecha_fin');

        if (! is_string($inicio) || ! is_string($fin) || $inicio === '' || $fin === '') {
            return null;
        }

        try {
            $desde = Carbon::parse($inicio);
            $hasta = Carbon::parse($fin);
        } catch (Exception) {
            return null;
        }

        return $hasta->lessThan($desde) ? null : [$desde, $hasta];
    }

    /**
     * Los días corridos del periodo que se está digitando, ya escritos como
     * «3 días», o nulo mientras no haya periodo.
     *
     * Es la respuesta de incapacidades (6.3) y de licencias (6.5), que cuentan
     * con la misma regla del motor. Vacaciones no la usa: cuenta días hábiles,
     * que es una regla distinta y con nombre distinto —la regla 3 del proyecto—.
     */
    public static function diasCorridos(Request $peticion): ?string
    {
        $periodo = self::de($peticion);

        if ($periodo === null) {
            return null;
        }

        [$desde, $hasta] = $periodo;

        return Formato::dias(MotorDeCalculo::diasCorridos($desde, $hasta));
    }
}
