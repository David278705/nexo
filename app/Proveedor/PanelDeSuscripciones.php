<?php

namespace App\Proveedor;

use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Presentacion\Formato;
use Illuminate\Support\Collection;

/**
 * La pantalla «Suscripciones» del panel del proveedor (documento 4, capítulo 6 y
 * `prototipos/panel-proveedor.html`).
 *
 * Lo que enseña, en el orden del prototipo: el ingreso recurrente mensual, el
 * aviso de quien está en mora, la tabla de todas las suscripciones y las tres
 * tarjetas —planes, distribución por plan y la escalera de mora—.
 *
 * **Las acciones sobre una suscripción no están aquí y es el propio prototipo el
 * que lo decide**: la única acción de su tabla es «ver historial». Registrar un
 * pago, cambiar de plan, suspender y reactivar viven en la pestaña «Suscripción»
 * de la ficha de la empresa (4.3), que es donde se mira un cliente concreto con
 * su historial delante. Esta pantalla es la del negocio entero.
 *
 * Lo único que sí se escribe desde aquí es lo que es de todos los clientes a la
 * vez: el catálogo de planes, y abrirle suscripción a una empresa que todavía no
 * tiene ninguna.
 */
class PanelDeSuscripciones
{
    /**
     * Lo que dice la tarjeta de planes mientras el catálogo esté vacío.
     *
     * No es un estado vacío cualquiera: es la decisión A.1 del documento 6
     * —«antes de construir la tabla de precios hay que calcular el costo real
     * por cliente»— dicha en el sitio donde alguien la va a necesitar.
     */
    public const SIN_PLANES = 'Todavía no hay ningún plan. La tabla de precios es una decisión '
        .'pendiente (documento 6, A.1): se calcula el costo por cliente y de ahí salen los '
        .'niveles, no al revés. Cuando estén, se escriben aquí y no en el código.';

    /**
     * Todas las props de la pantalla.
     *
     * @return array<string, mixed>
     */
    public static function paraLaPantalla(): array
    {
        return [
            'ingresoRecurrente' => self::ingresoRecurrente(),
            'aviso' => self::avisoDeMora(),
            'suscripciones' => self::filas(),
            'planes' => self::planes(),
            'distribucion' => self::distribucionPorPlan(),
            'escalera' => EscaleraDeMora::peldanos(),
            'empresasSinSuscripcion' => self::empresasSinSuscripcion(),
            'mediosDePago' => MedioDePago::paraElegir(),
            'planesParaElegir' => self::planesParaElegir(),
            'sinPlanes' => self::SIN_PLANES,
        ];
    }

    /**
     * «$ 4.180.000 de ingreso recurrente mensual», como el prototipo.
     */
    public static function ingresoRecurrente(): string
    {
        return Formato::dinero(self::ingresoRecurrenteMensual());
    }

    /**
     * El mismo total, sin formatear: lo que necesita el inicio del panel
     * (F5-06, documento 4, 3.2) para comparar contra el mes anterior, algo
     * que no se puede hacer con el texto ya escrito.
     *
     * Suma solo las activas: una suscripción suspendida no está produciendo
     * ingreso, y contarla haría que el número más importante del negocio subiera
     * justo cuando un cliente deja de pagar.
     */
    public static function ingresoRecurrenteMensual(): float
    {
        $total = 0.0;

        foreach (self::vigentes() as $suscripcion) {
            if (! $suscripcion->estaSuspendida()) {
                $total += $suscripcion->valorMensual();
            }
        }

        return $total;
    }

    /**
     * El aviso de la cabecera, con la empresa que lleva más días de atraso.
     *
     * Una sola y no una lista: es un aviso para que alguien coja el teléfono
     * hoy, y cinco avisos apilados no se leen. Las demás se ven en la tabla, con
     * su celda en rojo.
     */
    public static function avisoDeMora(): ?string
    {
        $peor = null;
        $dias = 0;

        foreach (self::vigentes() as $suscripcion) {
            $atraso = $suscripcion->diasDeAtraso();

            if ($atraso > $dias) {
                $peor = $suscripcion;
                $dias = $atraso;
            }
        }

        if (! $peor instanceof Suscripcion) {
            return null;
        }

        $empresa = $peor->tenant()->first();

        return EscaleraDeMora::aviso(
            $empresa instanceof Tenant ? (string) $empresa->getAttribute('nombre') : 'Una empresa',
            $dias,
        );
    }

    /**
     * Las filas de la tabla: las siete columnas del prototipo.
     *
     * @return list<array<string, mixed>>
     */
    public static function filas(): array
    {
        $filas = [];

        foreach (self::vigentes() as $suscripcion) {
            $empresa = $suscripcion->tenant()->first();
            $plan = $suscripcion->plan()->first();
            $cobro = $suscripcion->cobro();
            $estado = $suscripcion->estado;

            $filas[] = [
                'id' => $suscripcion->getKey(),
                'empresaId' => $suscripcion->getAttribute('tenant_id'),
                'empresa' => $empresa instanceof Tenant
                    ? (string) $empresa->getAttribute('nombre')
                    : 'Empresa eliminada',
                'plan' => $plan instanceof Plan ? $plan->nombre : Formato::VACIO,
                'valorMensual' => Formato::dinero($suscripcion->valorMensual()),
                'proximoCobro' => Formato::fecha($suscripcion->proxima_fecha_cobro),
                'medioDePago' => $suscripcion->medio_de_pago->etiqueta(),
                'cobro' => $suscripcion->textoDelCobro(),
                'cobroTono' => $cobro->tono(),
                'estado' => $estado->etiqueta(),
                'estadoTono' => $estado->tono(),
            ];
        }

        return $filas;
    }

    /**
     * La tarjeta «Planes»: el catálogo entero, incluido lo desactivado.
     *
     * Se enseña también lo desactivado por lo mismo que en los catálogos del
     * capítulo 8: esta es la pantalla desde la que se vuelve a activar, y lo que
     * desaparece de la vista no se puede recuperar.
     *
     * @return list<array<string, mixed>>
     */
    public static function planes(): array
    {
        return Plan::query()
            ->orderBy('precio_mensual')
            ->get()
            ->map(fn (Plan $plan): array => [
                'id' => $plan->getKey(),
                'nombre' => $plan->nombre,
                'precio' => Formato::dinero($plan->precio_mensual),
                'precioMensual' => (string) $plan->precio_mensual,
                'empleadosIncluidos' => $plan->empleados_incluidos,
                'usuariosIncluidos' => $plan->usuarios_incluidos,
                'cupos' => 'Hasta '.$plan->empleados_incluidos.' empleados · '
                    .$plan->usuarios_incluidos.' usuarios',
                'activo' => $plan->activo,
                'estado' => $plan->activo ? 'Activo' : 'Desactivado',
                'tono' => $plan->activo ? 'exito' : 'neutro',
                'contratado' => $plan->suscripciones()->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * La tarjeta «Distribución por plan»: cuántos clientes tiene cada uno.
     *
     * @return list<array{etiqueta: string, cuantos: int}>
     */
    public static function distribucionPorPlan(): array
    {
        return Plan::query()
            ->orderBy('precio_mensual')
            ->get()
            ->map(fn (Plan $plan): array => [
                'etiqueta' => $plan->nombre,
                'cuantos' => $plan->suscripciones()->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * Las empresas a las que todavía no se les ha abierto suscripción.
     *
     * El ambiente de demostración queda fuera: es una empresa ficticia del
     * proveedor y cobrarle una suscripción no significa nada.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public static function empresasSinSuscripcion(): array
    {
        return Tenant::query()
            ->deClientes()
            ->whereDoesntHave('suscripcion')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Tenant $empresa): array => [
                'valor' => (string) $empresa->getKey(),
                'texto' => (string) $empresa->getAttribute('nombre'),
            ])
            ->values()
            ->all();
    }

    /**
     * Los planes que se pueden contratar hoy, para los dos selectores: el de
     * abrir una suscripción y el de cambiar de plan.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public static function planesParaElegir(): array
    {
        return Plan::query()
            ->activos()
            ->orderBy('precio_mensual')
            ->get()
            ->map(fn (Plan $plan): array => [
                'valor' => (string) $plan->getKey(),
                'texto' => $plan->comoSeLee().' · '.Formato::dinero($plan->precio_mensual),
            ])
            ->values()
            ->all();
    }

    /**
     * Las suscripciones vivas, con su empresa y su plan ya cargados.
     *
     * @return Collection<int, Suscripcion>
     */
    private static function vigentes(): Collection
    {
        return Suscripcion::query()
            ->with(['tenant', 'plan'])
            ->orderBy('proxima_fecha_cobro')
            ->get();
    }
}
