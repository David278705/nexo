<?php

namespace App\Proveedor;

use App\Contratacion\OpcionesDeLista;
use App\Models\Tenant;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * El indicador de salud de uso del listado de empresas (documento 4, 4.1).
 *
 * Tres estados, los del documento, y **uno solo lo decide**: cuándo entró
 * alguien de esa empresa por última vez. El capítulo 16 explica por qué es ese y
 * no otro: «la frecuencia de entrada es la señal más temprana de cancelación».
 *
 * Los días de cada franja no se escriben aquí sino en `config/panel.php`, con el
 * porqué de la propuesta y la advertencia de que es una decisión comercial
 * todavía sin confirmar.
 *
 * Nunca haber entrado es «sin uso» y no un estado aparte: para la conversación
 * de venta —llamar antes de que se vaya— un cliente que no ha entrado nunca y
 * uno que lleva dos meses sin entrar necesitan la misma llamada. Lo que los
 * distingue se lee en la columna de al lado, «Último ingreso», que dice «Nunca».
 */
enum SaludDeUso: string
{
    use OpcionesDeLista;

    case Activa = 'activa';

    case UsoBajo = 'uso_bajo';

    case SinUso = 'sin_uso';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::UsoBajo => 'Uso bajo',
            self::SinUso => 'Sin uso',
        };
    }

    /**
     * El color de la etiqueta. Verde, ámbar y rojo, como el prototipo.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Activa => 'exito',
            self::UsoBajo => 'advertencia',
            self::SinUso => 'error',
        };
    }

    /**
     * En qué franja cae una empresa según su último ingreso.
     */
    public static function segunElUltimoIngreso(?Carbon $ultimoIngreso): self
    {
        if (! $ultimoIngreso instanceof Carbon) {
            return self::SinUso;
        }

        if ($ultimoIngreso >= self::limite('dias_de_actividad')) {
            return self::Activa;
        }

        return $ultimoIngreso <= self::limite('dias_sin_uso') ? self::SinUso : self::UsoBajo;
    }

    /**
     * El mismo reparto, pero como condición de la consulta del listado.
     *
     * Está aquí al lado del anterior y no en el listado a propósito: son la
     * misma regla contada dos veces —una para clasificar una fila y otra para
     * filtrar muchas—, y separarlas es la forma segura de que un día el filtro
     * «sin uso» devuelva empresas que la tabla pinta en verde.
     *
     * @param  Builder<Tenant>  $consulta
     */
    public function filtrar(Builder $consulta, string $columna): void
    {
        match ($this) {
            self::Activa => $consulta->where($columna, '>=', self::limite('dias_de_actividad')),
            self::SinUso => $consulta->where(fn (Builder $sinUso) => $sinUso
                ->whereNull($columna)
                ->orWhere($columna, '<=', self::limite('dias_sin_uso'))),
            self::UsoBajo => $consulta
                ->where($columna, '<', self::limite('dias_de_actividad'))
                ->where($columna, '>', self::limite('dias_sin_uso')),
        };
    }

    /**
     * La fecha a partir de la cual empieza una franja, contada por días
     * completos: quien entró ayer a las once de la noche lleva un día sin
     * entrar, no medio.
     */
    private static function limite(string $clave): Carbon
    {
        return Carbon::now()->startOfDay()->subDays(self::dias($clave));
    }

    /**
     * Cómo se lee el último ingreso en la columna de al lado.
     *
     * «Nunca» y no una raya: la raya diría que el dato falta, y aquí el dato
     * está y es que no ha entrado nadie.
     */
    public static function textoDelUltimoIngreso(?Carbon $ultimoIngreso): string
    {
        return $ultimoIngreso instanceof Carbon ? Formato::fecha($ultimoIngreso) : 'Nunca';
    }

    private static function dias(string $clave): int
    {
        return (int) config("panel.salud_de_uso.{$clave}");
    }
}
