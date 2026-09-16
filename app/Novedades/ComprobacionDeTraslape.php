<?php

namespace App\Novedades;

use App\Licencias\TipoDeLicencia;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\Vacacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * Si una novedad nueva o corregida se pisa con otra ya registrada del mismo
 * empleado (documento 1, 6.2, y el caso real que abrió `HD-03`: David le
 * registró a la misma persona vacaciones del 2 al 11 de septiembre y otras
 * del 10 al 24, y el sistema contó el 10 y el 11 dos veces contra el saldo).
 *
 * Vive en un solo sitio porque `RegistrarVacacion`, `RegistrarIncapacidad` y
 * `RegistrarLicencia` —y sus gemelas `Editar*`— tenían que decidir la misma
 * pregunta. No es un método genérico con un parámetro «tipo»: son tres,
 * porque lo que cada novedad comprueba no es igual:
 *
 * - **Vacaciones y licencias se pisan entre sí, y cada una consigo misma**:
 *   «o está suspendida o está en vacaciones» del caso de arriba, y dos
 *   periodos de la misma novedad tampoco pueden coexistir.
 * - **Ninguna de las dos se puede montar sobre una incapacidad ya
 *   registrada**: «sobre una incapacidad no se monta nada».
 * - **La incapacidad no comprueba vacaciones ni licencias.** El caso
 *   contrario —una incapacidad que llega después y cae sobre vacaciones ya
 *   registradas— no se bloquea aquí: la incapacidad manda y son las
 *   vacaciones las que se ajustan (`HD-04`). Por eso `paraIncapacidad()` solo
 *   compara contra otras incapacidades.
 *
 * El tenant y el borrado lógico no se filtran aquí a mano: `Vacacion`,
 * `Incapacidad` y `Licencia` llevan `PerteneceATenant` y `SoftDeletes`, así
 * que una consulta sin más ya excluye la empresa ajena y la fila eliminada.
 */
final class ComprobacionDeTraslape
{
    public static function paraVacaciones(
        int $vinculacionId,
        Carbon $inicio,
        Carbon $fin,
        ?int $idAExcluir = null,
    ): ?string {
        $vacacion = self::vacacionQueSePisa($vinculacionId, $inicio, $fin, $idAExcluir);

        if ($vacacion !== null) {
            return self::conVacacion($vacacion);
        }

        $incapacidad = self::incapacidadQueSePisa($vinculacionId, $inicio, $fin);

        if ($incapacidad !== null) {
            return self::sobreIncapacidad($incapacidad);
        }

        $licencia = self::licenciaQueSePisa($vinculacionId, $inicio, $fin);

        return $licencia === null ? null : self::conLicencia($licencia);
    }

    public static function paraIncapacidad(
        int $vinculacionId,
        Carbon $inicio,
        Carbon $fin,
        ?int $idAExcluir = null,
    ): ?string {
        $incapacidad = self::incapacidadQueSePisa($vinculacionId, $inicio, $fin, $idAExcluir);

        return $incapacidad === null ? null : self::conOtraIncapacidad($incapacidad);
    }

    public static function paraLicencia(
        int $vinculacionId,
        Carbon $inicio,
        Carbon $fin,
        ?int $idAExcluir = null,
    ): ?string {
        $vacacion = self::vacacionQueSePisa($vinculacionId, $inicio, $fin);

        if ($vacacion !== null) {
            return self::conVacacion($vacacion);
        }

        $incapacidad = self::incapacidadQueSePisa($vinculacionId, $inicio, $fin);

        if ($incapacidad !== null) {
            return self::sobreIncapacidad($incapacidad);
        }

        $licencia = self::licenciaQueSePisa($vinculacionId, $inicio, $fin, $idAExcluir);

        return $licencia === null ? null : self::conLicencia($licencia);
    }

    private static function vacacionQueSePisa(
        int $vinculacionId,
        Carbon $inicio,
        Carbon $fin,
        ?int $idAExcluir = null,
    ): ?Vacacion {
        return Vacacion::query()
            ->where('vinculacion_id', $vinculacionId)
            ->where('fecha_inicio', '<=', $fin)
            ->where('fecha_fin', '>=', $inicio)
            ->when($idAExcluir !== null, fn ($consulta) => $consulta->whereKeyNot($idAExcluir))
            ->first();
    }

    private static function incapacidadQueSePisa(
        int $vinculacionId,
        Carbon $inicio,
        Carbon $fin,
        ?int $idAExcluir = null,
    ): ?Incapacidad {
        return Incapacidad::query()
            ->where('vinculacion_id', $vinculacionId)
            ->where('fecha_inicio', '<=', $fin)
            ->where('fecha_fin', '>=', $inicio)
            ->when($idAExcluir !== null, fn ($consulta) => $consulta->whereKeyNot($idAExcluir))
            ->first();
    }

    private static function licenciaQueSePisa(
        int $vinculacionId,
        Carbon $inicio,
        Carbon $fin,
        ?int $idAExcluir = null,
    ): ?Licencia {
        return Licencia::query()
            ->where('vinculacion_id', $vinculacionId)
            ->where('fecha_inicio', '<=', $fin)
            ->where('fecha_fin', '>=', $inicio)
            ->when($idAExcluir !== null, fn ($consulta) => $consulta->whereKeyNot($idAExcluir))
            ->first();
    }

    private static function conVacacion(Vacacion $vacacion): string
    {
        return 'Estas fechas se pisan con unas vacaciones ya registradas, '
            .Formato::periodo($vacacion->fecha_inicio, $vacacion->fecha_fin).'.';
    }

    /**
     * El mensaje de una novedad nueva que cae sobre una incapacidad —el caso
     * que bloquea de verdad, con la razón dicha en la misma frase.
     */
    private static function sobreIncapacidad(Incapacidad $incapacidad): string
    {
        return 'Estas fechas se pisan con una incapacidad ya registrada, '
            .Formato::periodo($incapacidad->fecha_inicio, $incapacidad->fecha_fin)
            .'. Sobre una incapacidad no se puede registrar otra novedad.';
    }

    /**
     * El mensaje de dos incapacidades que se pisan entre sí: no hace falta la
     * segunda frase de `sobreIncapacidad()` porque las dos son incapacidades,
     * no una novedad distinta montándose sobre esta.
     */
    private static function conOtraIncapacidad(Incapacidad $incapacidad): string
    {
        return 'Estas fechas se pisan con otra incapacidad ya registrada, '
            .Formato::periodo($incapacidad->fecha_inicio, $incapacidad->fecha_fin).'.';
    }

    private static function conLicencia(Licencia $licencia): string
    {
        return 'Estas fechas se pisan con '.self::nombreDeLicencia($licencia->tipo).' ya registrada, '
            .Formato::periodo($licencia->fecha_inicio, $licencia->fecha_fin).'.';
    }

    private static function nombreDeLicencia(TipoDeLicencia $tipo): string
    {
        return match ($tipo) {
            TipoDeLicencia::LicenciaNoRemunerada => 'una licencia no remunerada',
            TipoDeLicencia::Ausencia => 'una ausencia',
            TipoDeLicencia::Suspension => 'una suspensión',
        };
    }
}
