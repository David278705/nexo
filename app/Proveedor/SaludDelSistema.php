<?php

namespace App\Proveedor;

use App\Models\EjecucionDeAlertas;
use App\Models\ErrorDeAplicacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * La pantalla «Salud del sistema» del panel del proveedor (documento 4,
 * capítulo 9): «lo que hay que mirar cada mañana en menos de un minuto».
 *
 * Cuatro de las cinco piezas son reales y se leen de datos que existen:
 * el historial de ejecuciones (`EjecucionDeAlertas`, nuevo de esta tarea), la
 * cola (`ColaDeTrabajos`, sobre las tablas estándar de Laravel), los errores
 * de aplicación (`ErrorDeAplicacion`, nuevo) y el espacio de la base de datos,
 * que Postgres ya sabe responder sin ninguna tabla propia.
 *
 * **La quinta —copias de seguridad— ya no depende de nada abierto.** La
 * decisión A.5 del documento 6 quedó resuelta el 23 de agosto de 2026:
 * Railway hace *Point-in-Time Recovery* nativo sobre PostgreSQL. No hay una
 * hora de «última copia» que consultar —es WAL continuo, no una corrida
 * puntual—, así que el texto dice el mecanismo real en vez de inventar un
 * «03:15, automática, de hoy» que el capítulo 16 del documento 4 prohíbe.
 */
final class SaludDelSistema
{
    /**
     * Cuántas ejecuciones recientes se enseñan en la tabla del historial.
     */
    private const EJECUCIONES_RECIENTES = 10;

    /**
     * Cuántos errores recientes se enseñan en la lista.
     */
    private const ERRORES_RECIENTES = 20;

    /**
     * Todas las props de la pantalla.
     *
     * @return array<string, mixed>
     */
    public static function paraLaPantalla(): array
    {
        return [
            'proceso' => [
                'hoy' => self::procesoDeHoy(),
                'historial' => self::historialDelProceso(),
            ],
            'cola' => [
                'pendientes' => ColaDeTrabajos::pendientes(),
                'fallidos' => ColaDeTrabajos::fallidos(),
            ],
            'errores' => [
                'ultimas24h' => self::erroresUltimoDia(),
                'filas' => self::erroresRecientes(),
            ],
            'copias' => [
                'disponible' => true,
                'texto' => 'Railway hace copias automáticas de la base de datos con Point-in-Time '
                    .'Recovery —WAL continuo, retención de 7 días— (documento 6, A.5). Restaurar a '
                    .'un punto en el tiempo crea un servicio nuevo al lado del que está en '
                    .'producción; el original sigue sirviendo sin tocarse durante la prueba.',
            ],
            'espacioBd' => self::espacioDeLaBaseDeDatos(),
            'correosDelMes' => ConsolaDeAlertas::entregabilidadDelMes()['enviadas'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function procesoDeHoy(): ?array
    {
        $ejecucion = EjecucionDeAlertas::query()
            ->where('fecha', Carbon::today()->toDateString())
            ->orderByDesc('ejecutada_en')
            ->first();

        return $ejecucion instanceof EjecucionDeAlertas ? self::filaDeEjecucion($ejecucion) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function historialDelProceso(): array
    {
        return EjecucionDeAlertas::query()
            ->orderByDesc('ejecutada_en')
            ->limit(self::EJECUCIONES_RECIENTES)
            ->get()
            ->map(fn (EjecucionDeAlertas $ejecucion): array => self::filaDeEjecucion($ejecucion))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaDeEjecucion(EjecucionDeAlertas $ejecucion): array
    {
        return [
            'fecha' => Formato::fecha($ejecucion->fecha),
            'hora' => $ejecucion->ejecutada_en->translatedFormat('g:i a'),
            'duracion' => self::duracion($ejecucion->duracion_ms),
            'resultado' => $ejecucion->exitosa
                ? $ejecucion->alertas_nuevas.' alertas'
                : $ejecucion->empresas_fallidas.' fallidas',
            'exitosa' => $ejecucion->exitosa,
        ];
    }

    /**
     * «41 s» o «2 m 10 s», que es como el prototipo lee la duración.
     */
    private static function duracion(int $ms): string
    {
        $segundos = intdiv($ms, 1000);
        $minutos = intdiv($segundos, 60);
        $resto = $segundos % 60;

        return $minutos > 0 ? $minutos.' m '.$resto.' s' : $segundos.' s';
    }

    private static function erroresUltimoDia(): int
    {
        return ErrorDeAplicacion::query()
            ->where('ocurrido_en', '>=', Carbon::now()->subDay())
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function erroresRecientes(): array
    {
        return ErrorDeAplicacion::query()
            ->orderByDesc('ocurrido_en')
            ->limit(self::ERRORES_RECIENTES)
            ->get()
            ->map(fn (ErrorDeAplicacion $error): array => [
                'ocurrido' => Formato::fechaYHora($error->ocurrido_en),
                'clase' => class_basename($error->clase),
                'mensaje' => $error->mensaje,
                'contexto' => $error->contexto,
            ])
            ->values()
            ->all();
    }

    /**
     * El tamaño de la base de datos, tal como lo sabe Postgres. Es la única
     * de las cinco piezas del capítulo 9 que no necesita ninguna tabla propia:
     * la respuesta ya existe en el motor.
     */
    private static function espacioDeLaBaseDeDatos(): string
    {
        $bytes = (int) DB::selectOne('select pg_database_size(current_database()) as bytes')->bytes;

        return match (true) {
            $bytes >= 1_073_741_824 => Formato::numeroDecimal($bytes / 1_073_741_824).' GB',
            default => Formato::numeroDecimal($bytes / 1_048_576).' MB',
        };
    }
}
