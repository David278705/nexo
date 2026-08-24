<?php

namespace App\Proveedor;

use App\Models\Pago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Todo lo que se escribe de una suscripción (documento 4, capítulo 6).
 *
 * Es la gemela de `AccesosDePrueba` para el capítulo 6: la pantalla y el
 * controlador no escriben nada, preguntan aquí. Cinco acciones y ninguna más:
 * registrar la suscripción, cambiarle el plan, registrar un pago, anularlo, y
 * suspender y reactivar.
 *
 * **Suspender y reactivar mueven dos filas y por eso van en una transacción.**
 * La suscripción cambia de estado y la empresa también, porque lo que bloquea el
 * acceso es el estado de la empresa —lo lee `BloquearEmpresaSuspendida` en cada
 * petición— y dejar las dos desalineadas produciría lo peor de los dos mundos:
 * un cliente cortado sin suscripción suspendida, o al revés.
 *
 * **Lo que suspender no hace**, con las palabras del propio capítulo 6:
 * «bloquea el acceso al sistema pero no elimina nada y no detiene los cálculos
 * internos. Al reactivar, todo sigue donde estaba». Aquí no hay un solo
 * `delete`, ni se toca una fila de negocio del cliente, ni se detiene ningún
 * proceso: los saldos siguen acumulando y el histórico sigue completo. La única
 * cosa que un cliente suspendido no puede hacer es entrar.
 *
 * La constancia no se escribe a mano: el observador de auditoría deja la línea
 * de cada uno de los dos cambios, con el `owner` como autor y **con la empresa
 * tocada como dueña de la línea**, que es lo que hace que el cliente pueda ver
 * en su propia auditoría que se le suspendió el servicio y por qué. El motivo,
 * además, se guarda en la suscripción, que es donde se lee sin salir de la
 * pantalla.
 */
class Suscripciones
{
    /**
     * Qué implica suspender, con lo que el capítulo 6 pide tener claro antes de
     * hacerlo. Se enseña en la ficha, al lado del botón, y no en una nota al pie
     * de la documentación: es la advertencia que evita que alguien crea que
     * suspender borra o que retener la información es una opción.
     */
    public const AVISO_DE_LA_SUSPENSION = 'Suspender bloquea el acceso al sistema pero no elimina '
        .'nada y no detiene los cálculos internos: al reactivar, todo sigue donde estaba. Lo que '
        .'nunca se hace es retener la información como forma de presión; el cliente en mora puede '
        .'pedir su información y hay que entregársela.';

    /**
     * Le abre suscripción a una empresa que hasta hoy no tenía ninguna.
     *
     * Las dos fechas se piden y no se deducen: «desde cuándo es cliente de
     * pago» y «cuándo es el próximo cobro» no tienen por qué ser el mismo día
     * —hay clientes que arrancan con un mes cortesía y otros que pagan
     * adelantado—, y deducir una de la otra sería inventarle a David una
     * política de facturación que no está escrita en ninguna parte.
     */
    public function registrar(
        Tenant $empresa,
        Plan $plan,
        Carbon $iniciaEl,
        Carbon $proximoCobro,
        MedioDePago $medioDePago,
    ): Suscripcion {
        return DB::transaction(function () use ($empresa, $plan, $iniciaEl, $proximoCobro, $medioDePago): Suscripcion {
            /** @var Suscripcion $suscripcion */
            $suscripcion = Suscripcion::query()->create([
                'tenant_id' => $empresa->getKey(),
                'plan_id' => $plan->getKey(),
                'inicia_el' => $iniciaEl,
                'plan_aplica_desde' => $iniciaEl,
                'proxima_fecha_cobro' => $proximoCobro,
                'medio_de_pago' => $medioDePago->value,
                'estado' => EstadoDeSuscripcion::Activa->value,
            ]);

            $this->copiarElPlanALaEmpresa($empresa, $plan);

            return $suscripcion;
        });
    }

    /**
     * El cambio de plan del capítulo 6, «con la fecha desde la que aplica».
     *
     * No abre una fila nueva: el plan vigente es uno solo y su historia queda en
     * la auditoría, que registra el plan anterior y el nuevo con su autor y su
     * fecha. La fecha desde la que aplica se guarda porque es la que decide
     * desde cuándo se factura el valor nuevo, y esa no es la del cambio: se
     * puede acordar hoy un plan que arranca el mes entrante.
     */
    public function cambiarDePlan(Suscripcion $suscripcion, Plan $plan, Carbon $aplicaDesde): void
    {
        DB::transaction(function () use ($suscripcion, $plan, $aplicaDesde): void {
            $suscripcion->forceFill([
                'plan_id' => $plan->getKey(),
                'plan_aplica_desde' => $aplicaDesde,
            ])->save();

            $empresa = $suscripcion->tenant()->first();

            if ($empresa instanceof Tenant) {
                $this->copiarElPlanALaEmpresa($empresa, $plan);
            }
        });
    }

    /**
     * Copia a la empresa el nombre y los dos cupos del plan contratado.
     *
     * **No es una segunda versión del plan, es la misma.** Las columnas
     * `plan_*` de `tenants` existen desde F1B-02, cuando no había catálogo:
     * guardaban lo que se le pactó al cliente en la reunión de onboarding, y de
     * ellas leen la columna «Plan» del listado de empresas, su filtro, el
     * resaltado de «empleados sobre el plan» y el resumen que se le lee al
     * cliente. Desde que existe el catálogo, quien manda es la suscripción, así
     * que se copian aquí en la misma transacción: dos sitios que digan planes
     * distintos es exactamente el problema que hay que evitar.
     *
     * `plan_inicia_el` no se toca: esa es la fecha del alta comercial y no la
     * del plan de hoy, que es `plan_aplica_desde` en la suscripción.
     */
    private function copiarElPlanALaEmpresa(Tenant $empresa, Plan $plan): void
    {
        $empresa->forceFill([
            'plan_nombre' => $plan->nombre,
            'plan_empleados_incluidos' => $plan->empleados_incluidos,
            'plan_usuarios_incluidos' => $plan->usuarios_incluidos,
        ])->save();
    }

    /**
     * Vuelve a copiar un plan corregido a las empresas que lo tienen
     * contratado.
     *
     * Es la otra mitad de `copiarElPlanALaEmpresa()`: sin esto, corregirle el
     * nombre o los cupos a un plan dejaría a sus clientes con la versión vieja
     * en el listado de empresas, que es donde se mira el exceso de plan.
     */
    public function refrescarElPlanEnLasEmpresas(Plan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            foreach ($plan->suscripciones()->with('tenant')->get() as $suscripcion) {
                $empresa = $suscripcion->tenant()->first();

                if ($empresa instanceof Tenant) {
                    $this->copiarElPlanALaEmpresa($empresa, $plan);
                }
            }
        });
    }

    /**
     * Registra la plata que entró y adelanta el cobro un mes.
     *
     * El mes es del plan y no de esta clase: el capítulo 6 define los planes por
     * su precio mensual y el capítulo 17.3 habla de facturar suscripciones
     * recurrentes. Se cuenta desde la fecha que tenía el cobro y no desde hoy,
     * para que pagar tarde no corra el calendario de cobro del cliente: quien
     * paga el 5 lo que debía el 1 sigue debiendo el 1 del mes siguiente.
     *
     * `addMonthNoOverflow` es a propósito: el cobro del 31 de enero cae el 28 de
     * febrero y no el 3 de marzo.
     */
    public function registrarPago(
        Suscripcion $suscripcion,
        float $monto,
        Carbon $fecha,
        ?string $numeroFactura = null,
    ): Pago {
        return DB::transaction(function () use ($suscripcion, $monto, $fecha, $numeroFactura): Pago {
            /** @var Pago $pago */
            $pago = Pago::query()->create([
                'suscripcion_id' => $suscripcion->getKey(),
                'monto' => $monto,
                'fecha' => $fecha,
                'estado' => EstadoDeFactura::Pagada->value,
                'numero_factura' => $numeroFactura,
            ]);

            $suscripcion->forceFill([
                'proxima_fecha_cobro' => $suscripcion->proxima_fecha_cobro->clone()->addMonthNoOverflow(),
            ])->save();

            return $pago;
        });
    }

    /**
     * Anula un pago mal registrado y deshace exactamente lo que hizo
     * registrarlo: devuelve el cobro al mes anterior.
     *
     * No se borra la fila —regla 4 del proyecto—: queda en el historial con su
     * número de factura y su estado, porque un número que desaparece es lo que
     * un contador no puede explicar.
     */
    public function anularPago(Pago $pago): void
    {
        DB::transaction(function () use ($pago): void {
            $suscripcion = $pago->suscripcion()->first();

            $pago->forceFill(['estado' => EstadoDeFactura::Anulada->value])->save();

            if ($suscripcion instanceof Suscripcion) {
                $suscripcion->forceFill([
                    'proxima_fecha_cobro' => $suscripcion->proxima_fecha_cobro->clone()->subMonthNoOverflow(),
                ])->save();
            }
        });
    }

    /**
     * Suspende el servicio: la suscripción y la empresa, en una transacción.
     *
     * El motivo es obligatorio y llega escrito por quien suspende. No hay motivo
     * por omisión ni una lista de motivos: el documento pide «motivo y
     * constancia» y los motivos de una suspensión —mora, cierre de la empresa,
     * acuerdo de pago incumplido— no son una lista cerrada que se pueda adivinar.
     */
    public function suspender(Suscripcion $suscripcion, string $motivo): void
    {
        DB::transaction(function () use ($suscripcion, $motivo): void {
            $suscripcion->forceFill([
                'estado' => EstadoDeSuscripcion::Suspendida->value,
                'suspendida_en' => Carbon::now(),
                'motivo_de_suspension' => $motivo,
                // Se limpia el motivo de la reactivación anterior: si no, la
                // pantalla enseñaría a la vez «suspendida hoy» y «reactivada
                // por» de hace tres meses, y las dos frases se contradicen.
                'reactivada_en' => null,
                'motivo_de_reactivacion' => null,
            ])->save();

            $empresa = $suscripcion->tenant()->first();

            if ($empresa instanceof Tenant) {
                $empresa->forceFill(['estado' => EstadoDeEmpresa::Suspendida->value])->save();
            }
        });
    }

    /**
     * Devuelve el servicio. Todo sigue donde estaba: no hay nada que restaurar
     * porque no se borró nada.
     */
    public function reactivar(Suscripcion $suscripcion, string $motivo): void
    {
        DB::transaction(function () use ($suscripcion, $motivo): void {
            $suscripcion->forceFill([
                'estado' => EstadoDeSuscripcion::Activa->value,
                'reactivada_en' => Carbon::now(),
                'motivo_de_reactivacion' => $motivo,
            ])->save();

            $empresa = $suscripcion->tenant()->first();

            if ($empresa instanceof Tenant) {
                $empresa->forceFill(['estado' => EstadoDeEmpresa::Activa->value])->save();
            }
        });
    }

    /**
     * La suscripción de una empresa, si la tiene.
     */
    public function de(Tenant $empresa): ?Suscripcion
    {
        $suscripcion = Suscripcion::query()
            ->where('tenant_id', $empresa->getKey())
            ->first();

        return $suscripcion instanceof Suscripcion ? $suscripcion : null;
    }

    /**
     * Lo que se le dice al owner después de registrar un pago, con la fecha
     * nueva escrita: es la única forma de que se entere de que el cobro se
     * movió, y de cuándo vuelve a tocar.
     */
    public static function confirmacionDePago(Suscripcion $suscripcion, string $empresa): string
    {
        return 'Se registró el pago de '.$empresa.'. El próximo cobro queda el '
            .Formato::fechaLarga($suscripcion->proxima_fecha_cobro).'.';
    }

    /**
     * Lo que se le dice al owner al suspender. Nombra la consecuencia y su
     * límite: es lo que el capítulo 6 advierte que hay que tener claro.
     */
    public static function confirmacionDeSuspension(string $empresa): string
    {
        return 'Se suspendió el servicio de '.$empresa.'. Nadie de esa empresa puede entrar, '
            .'y no se borró ni se detuvo nada: los cálculos siguen corriendo y al reactivar '
            .'todo sigue donde estaba. Si el cliente pide su información, hay que entregársela.';
    }

    public static function confirmacionDeReactivacion(string $empresa): string
    {
        return 'Se reactivó el servicio de '.$empresa.'. Sus usuarios vuelven a entrar '
            .'y encuentran todo donde lo dejaron.';
    }
}
