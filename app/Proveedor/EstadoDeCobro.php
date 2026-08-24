<?php

namespace App\Proveedor;

use App\Contratacion\OpcionesDeLista;
use App\Models\Suscripcion;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * La columna «Pago» del listado de empresas (documento 4, 4.1): «al día, por
 * vencer o en mora con los días de atraso».
 *
 * Es el gemelo de `SaludDeUso` para el otro indicador del listado, y se parece
 * en lo que importa: **una sola cosa lo decide** —la próxima fecha de cobro de
 * la suscripción frente a hoy— y los días de cada franja no se escriben aquí
 * sino en `config/panel.php`, porque son criterio comercial y no una regla que
 * se pueda consultar en ninguna norma.
 *
 * Una empresa sin suscripción no tiene ninguno de los tres estados y no es un
 * hueco: es una empresa a la que todavía no se le cobra —está en prueba, o se
 * creó antes de que existiera el catálogo de planes—. `sinSuscripcion()` dice
 * eso con palabras en la propia celda.
 */
enum EstadoDeCobro: string
{
    use OpcionesDeLista;

    /**
     * Lo que se lee en la celda de una empresa a la que no se le cobra
     * todavía. Está aquí y no en la pantalla porque es información y no
     * decoración.
     */
    public const SIN_SUSCRIPCION = 'Sin suscripción';

    case AlDia = 'al_dia';

    case PorVencer = 'por_vencer';

    case EnMora = 'en_mora';

    public function etiqueta(): string
    {
        return match ($this) {
            self::AlDia => 'Al día',
            self::PorVencer => 'Por vencer',
            self::EnMora => 'En mora',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::AlDia => 'exito',
            self::PorVencer => 'advertencia',
            self::EnMora => 'error',
        };
    }

    /**
     * En qué franja cae un cobro cuya próxima fecha es la que se pasa.
     *
     * El día mismo del cobro todavía es «por vencer» y no mora: la mora empieza
     * cuando la fecha ya pasó, que es lo que dice «días de atraso».
     */
    public static function segunElProximoCobro(Carbon $proximoCobro): self
    {
        $hoy = Carbon::today();

        if ($proximoCobro->lt($hoy)) {
            return self::EnMora;
        }

        return $hoy->diffInDays($proximoCobro) <= self::diasPorVencer()
            ? self::PorVencer
            : self::AlDia;
    }

    /**
     * Cuántos días de atraso lleva ese cobro. Cero si todavía no ha vencido.
     *
     * Es la mitad de la columna del documento —«en mora con los días de
     * atraso»— y la entrada de la escalera de `EscaleraDeMora`.
     */
    public static function diasDeAtraso(Carbon $proximoCobro): int
    {
        $hoy = Carbon::today();

        return $proximoCobro->lt($hoy) ? (int) $proximoCobro->diffInDays($hoy) : 0;
    }

    /**
     * Cómo se lee la celda: «Al día», «Vence hoy», «Vence en 3 días», «Mora de
     * 22 días».
     *
     * Los casos con número los escribe el servidor y no la pantalla, igual que
     * el resto de las referencias temporales del sistema: la pantalla no resta
     * fechas en ninguna parte.
     */
    public static function texto(Carbon $proximoCobro): string
    {
        $estado = self::segunElProximoCobro($proximoCobro);
        $faltan = (int) Carbon::today()->diffInDays($proximoCobro);

        return match (true) {
            $estado === self::EnMora => 'Mora de '.Formato::dias(self::diasDeAtraso($proximoCobro)),
            $estado === self::AlDia => $estado->etiqueta(),
            $faltan === 0 => 'Vence hoy',
            default => 'Vence en '.Formato::dias($faltan),
        };
    }

    /**
     * La misma regla, pero como condición de la consulta del listado.
     *
     * Está aquí al lado de `segunElProximoCobro()` por lo mismo que en
     * `SaludDeUso`: son la misma frontera contada dos veces, y separarlas es la
     * forma segura de que un día el filtro «en mora» devuelva empresas que la
     * tabla pinta en verde.
     *
     * @param  Builder<Suscripcion>  $consulta
     */
    public function filtrar(Builder $consulta): void
    {
        $hoy = Carbon::today();
        $limite = $hoy->clone()->addDays(self::diasPorVencer());

        match ($this) {
            self::EnMora => $consulta->where('proxima_fecha_cobro', '<', $hoy),
            self::PorVencer => $consulta
                ->where('proxima_fecha_cobro', '>=', $hoy)
                ->where('proxima_fecha_cobro', '<=', $limite),
            self::AlDia => $consulta->where('proxima_fecha_cobro', '>', $limite),
        };
    }

    private static function diasPorVencer(): int
    {
        return (int) config('panel.cobro.dias_por_vencer');
    }
}
