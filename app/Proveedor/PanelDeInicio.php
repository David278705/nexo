<?php

namespace App\Proveedor;

use App\Autenticacion\ResultadoDeAcceso;
use App\Autorizacion\Rol;
use App\Autorizacion\SeccionDelPanel;
use App\Models\Acceso;
use App\Models\EjecucionDeAlertas;
use App\Models\MetricaMensual;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Models\User;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * El inicio del panel del proveedor (F5-06, documento 4, capítulo 3): «qué
 * requiere atención hoy, y cómo va el negocio», en ese orden.
 *
 * Las seis tarjetas de atención (3.1) y los siete indicadores de negocio (3.2)
 * se apoyan en lo que ya construyeron las tareas anteriores —suscripciones
 * (F5-04), consola de envíos (F5-05), catálogos (F1B-04)— y en dos piezas
 * nuevas de esta: el historial de ejecuciones del proceso diario
 * (`EjecucionDeAlertas`) y la foto mensual del negocio (`MetricaMensual`),
 * que es lo único que hace posible «con la variación frente al mes anterior»
 * sin inventar un número.
 *
 * **El ambiente de demostración queda fuera de todo lo de aquí**, por el mismo
 * motivo que en `PanelDeSuscripciones::empresasSinSuscripcion()`: es una
 * empresa ficticia del proveedor, y contarla en el negocio o en el riesgo de
 * cancelación inflaría las cifras con datos que no son de ningún cliente.
 *
 * Ninguna consulta aquí cruza el aislamiento de una tabla de negocio: todo lo
 * que se lee es del propio proveedor —`tenants`, `accesos`, `suscripciones`,
 * `users`— salvo los correos del mes y las alertas fallidas, que vienen ya
 * resueltos de `ConsolaDeAlertas`, la única que sabe recorrer `alertas_enviadas`
 * empresa por empresa.
 */
final class PanelDeInicio
{
    /**
     * Los dos números de «cliente sin arrancar» (documento 4, 3.1): están
     * escritos en el propio texto del documento, no son criterio comercial,
     * así que van aquí como constantes y no en `config/panel.php`.
     */
    private const DIAS_SIN_ARRANCAR = 7;

    private const EMPLEADOS_MINIMOS_SIN_ARRANCAR = 5;

    /**
     * Todas las props de la pantalla.
     *
     * @return array<string, mixed>
     */
    public static function paraLaPantalla(): array
    {
        return [
            'atencion' => self::atencion(),
            'negocio' => self::negocio(),
        ];
    }

    /**
     * Las seis tarjetas del bloque «Requiere atención hoy», siempre las seis
     * y en el orden de la tabla del documento: la que no tiene nada que
     * avisar se enseña en verde, no se oculta.
     *
     * @return list<array<string, mixed>>
     */
    private static function atencion(): array
    {
        return [
            self::clientesEnRiesgo(),
            self::clientesSinArrancar(),
            self::pagosVencidos(),
            self::alertasFallidas(),
            self::festivosSinCargar(),
            self::procesosFallidos(),
        ];
    }

    /**
     * Los siete indicadores del bloque «Negocio», en el orden del documento.
     *
     * @return list<array<string, mixed>>
     */
    private static function negocio(): array
    {
        $empresasActivas = self::clientesReales()->where('estado', EstadoDeEmpresa::Activa->value)->count();
        $ingresoActual = PanelDeSuscripciones::ingresoRecurrenteMensual();
        $mesAnterior = MetricaMensual::query()
            ->where('mes', Carbon::now()->subMonthNoOverflow()->format('Y-m'))
            ->first();

        return [
            self::tarjetaConVariacion(
                'Empresas activas',
                (string) $empresasActivas,
                $mesAnterior?->empresas_activas,
                (float) $empresasActivas,
                false,
            ),
            self::tarjetaConVariacion(
                'Ingreso recurrente mensual',
                Formato::dinero($ingresoActual),
                $mesAnterior?->ingreso_recurrente,
                $ingresoActual,
                true,
            ),
            [
                'valor' => (string) self::empleadosTotales(),
                'etiqueta' => 'Empleados gestionados en total',
                'variacion' => '',
                'tendencia' => '',
                'nota' => 'Es lo que mueve el costo de infraestructura',
                'notaTono' => '',
            ],
            [
                'valor' => (string) self::usuariosAdministradores(),
                'etiqueta' => 'Usuarios administradores',
                'variacion' => '',
                'tendencia' => '',
                'nota' => 'La otra variable del precio',
                'notaTono' => '',
            ],
            self::altasYBajas(),
            self::cancelacionAcumulada(),
            [
                'valor' => (string) ConsolaDeAlertas::entregabilidadDelMes()['enviadas'],
                'etiqueta' => 'Correos enviados este mes',
                'variacion' => '',
                'tendencia' => '',
                'nota' => 'El costo que más escala',
                'notaTono' => '',
            ],
        ];
    }

    /**
     * «Empresas cuyo uso cayó de forma marcada frente al mes anterior».
     *
     * Se mide con los inicios de sesión exitosos de `accesos`, en dos
     * ventanas seguidas de treinta días: si la actual cae a la mitad o menos
     * de la anterior —y hubo algo que perder—, la empresa cuenta. Los dos
     * números de la comparación viven en `config('panel.riesgo_de_cancelacion')`
     * por lo que explica su comentario: no están en el documento.
     *
     * @return array<string, mixed>
     */
    private static function clientesEnRiesgo(): array
    {
        $ventana = (int) config('panel.riesgo_de_cancelacion.ventana_dias');
        $caida = (int) config('panel.riesgo_de_cancelacion.caida_minima_porcentaje');
        $hoy = Carbon::now();
        $actualDesde = $hoy->copy()->subDays($ventana);
        $anteriorDesde = $hoy->copy()->subDays($ventana * 2);

        $cuantas = 0;

        foreach (self::clientesReales()->get() as $empresa) {
            $actual = Acceso::query()
                ->where('tenant_id', $empresa->getKey())
                ->where('resultado', ResultadoDeAcceso::Exitoso->value)
                ->where('created_at', '>=', $actualDesde)
                ->count();

            $anterior = Acceso::query()
                ->where('tenant_id', $empresa->getKey())
                ->where('resultado', ResultadoDeAcceso::Exitoso->value)
                ->whereBetween('created_at', [$anteriorDesde, $actualDesde])
                ->count();

            if ($anterior > 0 && $actual <= $anterior * ($caida / 100)) {
                $cuantas++;
            }
        }

        return [
            'valor' => (string) $cuantas,
            'etiqueta' => $cuantas === 1
                ? 'Cliente en riesgo — el uso cayó de forma marcada'
                : 'Clientes en riesgo — el uso cayó de forma marcada',
            'enlace' => SeccionDelPanel::Empresas->url(),
            'accion' => 'Ver cuáles y llamarlos',
            'tono' => $cuantas > 0 ? 'error' : '',
        ];
    }

    /**
     * «Empresas creadas hace más de una semana con menos de cinco empleados
     * cargados» (documento 4, 3.1), en sus propias palabras.
     *
     * @return array<string, mixed>
     */
    private static function clientesSinArrancar(): array
    {
        $limite = Carbon::now()->subDays(self::DIAS_SIN_ARRANCAR);
        $cuantas = 0;

        foreach (self::clientesReales()->where('created_at', '<=', $limite)->get() as $empresa) {
            if (ListadoDeEmpresas::empleadosDe($empresa) < self::EMPLEADOS_MINIMOS_SIN_ARRANCAR) {
                $cuantas++;
            }
        }

        return [
            'valor' => (string) $cuantas,
            'etiqueta' => $cuantas === 1
                ? 'Cliente sin arrancar — creado hace más de una semana, con pocos empleados cargados'
                : 'Clientes sin arrancar — creados hace más de una semana, con pocos empleados cargados',
            'enlace' => SeccionDelPanel::Empresas->url(),
            'accion' => 'Ver ficha',
            'tono' => '',
        ];
    }

    /**
     * «Suscripciones en mora, con días de atraso».
     *
     * @return array<string, mixed>
     */
    private static function pagosVencidos(): array
    {
        $cuantas = 0;
        $peorAtraso = 0;

        foreach (Suscripcion::query()->get() as $suscripcion) {
            if ($suscripcion->cobro() === EstadoDeCobro::EnMora) {
                $cuantas++;
                $peorAtraso = max($peorAtraso, $suscripcion->diasDeAtraso());
            }
        }

        $etiqueta = match (true) {
            $cuantas === 0 => 'Ningún pago vencido',
            $cuantas === 1 => 'Pago vencido hace '.Formato::dias($peorAtraso),
            default => $cuantas.' pagos vencidos, el más antiguo hace '.Formato::dias($peorAtraso),
        };

        return [
            'valor' => (string) $cuantas,
            'etiqueta' => $etiqueta,
            'enlace' => SeccionDelPanel::Suscripciones->url(),
            'accion' => 'Revisar antes de suspender',
            'tono' => $cuantas > 0 ? 'error' : '',
        ];
    }

    /**
     * «Correos que no se pudieron entregar en las últimas 24 horas».
     *
     * @return array<string, mixed>
     */
    private static function alertasFallidas(): array
    {
        $cuantas = ConsolaDeAlertas::alertasFallidasUltimoDia();

        return [
            'valor' => (string) $cuantas,
            'etiqueta' => $cuantas === 1
                ? 'Alerta que no se pudo entregar en las últimas 24 horas'
                : 'Alertas que no se pudieron entregar en las últimas 24 horas',
            'enlace' => SeccionDelPanel::Alertas->url(),
            'accion' => 'Ver la consola de envíos',
            'tono' => '',
        ];
    }

    /**
     * «Aviso si el año siguiente no tiene calendario cargado. Aparece desde
     * octubre», reutilizando exactamente el aviso de F1B-04.
     *
     * @return array<string, mixed>
     */
    private static function festivosSinCargar(): array
    {
        $aviso = CatalogosDelSistema::avisoDeFestivos();

        if ($aviso === null) {
            return [
                'valor' => 'Al día',
                'etiqueta' => 'El calendario de festivos está completo',
                'enlace' => SeccionDelPanel::Catalogos->url(),
                'accion' => 'Ver calendario',
                'tono' => 'exito',
            ];
        }

        // El año que falta: el en curso si el aviso es el grave («error»), el
        // siguiente si es el que empieza a avisarse desde octubre.
        $anio = $aviso['tono'] === 'error' ? Carbon::now()->year : Carbon::now()->year + 1;

        return [
            'valor' => (string) $anio,
            'etiqueta' => 'Festivos de '.$anio.' sin cargar',
            'enlace' => SeccionDelPanel::Catalogos->url(),
            'accion' => 'Cargar calendario',
            // Solo el año en curso sin calendario es «algo está mal de
            // verdad»: el siguiente todavía tiene tiempo, y por eso no se
            // pinta de rojo.
            'tono' => $aviso['tono'] === 'error' ? 'error' : '',
        ];
    }

    /**
     * «Si el proceso diario de alertas no corrió o falló», leído de
     * `EjecucionDeAlertas`, el historial nuevo de esta tarea.
     *
     * @return array<string, mixed>
     */
    private static function procesosFallidos(): array
    {
        $enlace = SeccionDelPanel::Salud->url();
        $accion = 'Ver salud del sistema';

        $ejecucion = EjecucionDeAlertas::query()
            ->where('fecha', Carbon::today()->toDateString())
            ->orderByDesc('ejecutada_en')
            ->first();

        if (! $ejecucion instanceof EjecucionDeAlertas) {
            return [
                'valor' => 'Sin ejecutar',
                'etiqueta' => 'El proceso de alertas no ha corrido hoy',
                'enlace' => $enlace,
                'accion' => $accion,
                'tono' => 'error',
            ];
        }

        if (! $ejecucion->exitosa) {
            return [
                'valor' => (string) $ejecucion->empresas_fallidas,
                'etiqueta' => 'El proceso de hoy corrió con empresas que fallaron',
                'enlace' => $enlace,
                'accion' => $accion,
                'tono' => 'error',
            ];
        }

        return [
            'valor' => $ejecucion->ejecutada_en->translatedFormat('g:i a'),
            'etiqueta' => 'El proceso de alertas corrió hoy sin problemas',
            'enlace' => $enlace,
            'accion' => $accion,
            'tono' => 'exito',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function tarjetaConVariacion(
        string $etiqueta,
        string $valorTexto,
        ?float $anterior,
        float $actual,
        bool $esDinero,
    ): array {
        if ($anterior === null) {
            return [
                'valor' => $valorTexto,
                'etiqueta' => $etiqueta,
                'variacion' => '',
                'tendencia' => '',
                'nota' => 'Primer mes con historial: todavía no hay con qué comparar.',
                'notaTono' => '',
            ];
        }

        $delta = $actual - $anterior;
        $tendencia = $delta > 0 ? 'sube' : ($delta < 0 ? 'baja' : '');
        $signo = $delta > 0 ? '+' : ($delta < 0 ? '-' : '');
        $magnitud = $esDinero ? Formato::dinero(abs($delta)) : (string) abs((int) round($delta));

        return [
            'valor' => $valorTexto,
            'etiqueta' => $etiqueta,
            'variacion' => $signo === '' ? 'Sin cambios este mes' : $signo.' '.$magnitud.' este mes',
            'tendencia' => $tendencia,
            'nota' => '',
            'notaTono' => '',
        ];
    }

    private static function empleadosTotales(): int
    {
        $total = 0;

        foreach (self::clientesReales()->get() as $empresa) {
            $total += ListadoDeEmpresas::empleadosDe($empresa);
        }

        return $total;
    }

    private static function usuariosAdministradores(): int
    {
        return User::query()
            ->whereIn('rol', array_map(fn (Rol $rol): string => $rol->value, Rol::deEmpresa()))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private static function altasYBajas(): array
    {
        $desde = Carbon::now()->startOfMonth();
        $hasta = Carbon::now()->endOfMonth();

        $altas = self::clientesReales()->whereBetween('created_at', [$desde, $hasta])->count();

        // Una «baja» es una empresa dada de baja lógicamente (regla 4: nunca
        // borrado físico). Hoy ninguna pantalla ofrece esa acción, así que
        // este número es honesto en cero hasta que exista.
        $bajas = self::bajasEntre($desde, $hasta);

        return [
            'valor' => $altas.' / '.$bajas,
            'etiqueta' => 'Altas y bajas del mes',
            'variacion' => '',
            'tendencia' => '',
            'nota' => '',
            'notaTono' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cancelacionAcumulada(): array
    {
        $bajasDelAnio = self::bajasEntre(Carbon::now()->startOfYear(), Carbon::now()->endOfYear());
        $base = self::clientesReales()->count() + $bajasDelAnio;
        $tasa = $base === 0 ? 0.0 : ($bajasDelAnio / $base) * 100;

        return [
            'valor' => Formato::porcentaje($tasa),
            'etiqueta' => 'Cancelación acumulada del año',
            'variacion' => '',
            'tendencia' => '',
            'nota' => $bajasDelAnio.' de '.$base,
            'notaTono' => '',
        ];
    }

    private static function bajasEntre(Carbon $desde, Carbon $hasta): int
    {
        return Tenant::onlyTrashed()
            ->where('es_ambiente_demo', false)
            ->whereBetween('deleted_at', [$desde, $hasta])
            ->count();
    }

    /**
     * @return Builder<Tenant>
     */
    private static function clientesReales(): Builder
    {
        return Tenant::query()->where('es_ambiente_demo', false);
    }
}
