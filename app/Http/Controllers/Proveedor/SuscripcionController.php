<?php

namespace App\Http\Controllers\Proveedor;

use App\Autorizacion\SeccionDelPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarDePlan;
use App\Http\Requests\GuardarPlan;
use App\Http\Requests\MotivoDeLaSuscripcion;
use App\Http\Requests\RegistrarPago;
use App\Http\Requests\RegistrarSuscripcion;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Presentacion\Formato;
use App\Proveedor\MedioDePago;
use App\Proveedor\PanelDeSuscripciones;
use App\Proveedor\Suscripciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Suscripciones y cobros (documento 4, capítulo 6).
 *
 * Dos sitios y una división que viene del prototipo: **la pantalla de
 * Suscripciones es la del negocio entero** —el ingreso recurrente, la mora, la
 * tabla de todos los clientes y el catálogo de planes— y **las acciones sobre un
 * cliente concreto están en su ficha**, que es donde se miran con su historial
 * delante. Por eso las cuatro acciones de una suscripción vuelven a la ficha y
 * las dos del catálogo vuelven a la pantalla.
 *
 * Aquí no se escribe nada a mano: la validación son los cinco `FormRequest` y la
 * escritura es `App\Proveedor\Suscripciones`, que es quien mueve la suscripción y
 * la empresa en una sola transacción cuando hace falta.
 */
class SuscripcionController extends Controller
{
    public function __construct(private readonly Suscripciones $suscripciones) {}

    public function index(): Response
    {
        return Inertia::render('Proveedor/Suscripciones', [
            ...PanelDeSuscripciones::paraLaPantalla(),
            'avisoDeLaSuspension' => Suscripciones::AVISO_DE_LA_SUSPENSION,
        ]);
    }

    /**
     * Abre la suscripción de una empresa que todavía no tenía ninguna.
     */
    public function registrar(RegistrarSuscripcion $peticion): RedirectResponse
    {
        $empresa = Tenant::query()->findOrFail($peticion->integer('tenant_id'));
        $plan = Plan::query()->findOrFail($peticion->integer('plan_id'));

        $this->suscripciones->registrar(
            $empresa,
            $plan,
            Carbon::parse((string) $peticion->input('inicia_el')),
            Carbon::parse((string) $peticion->input('proxima_fecha_cobro')),
            MedioDePago::from((string) $peticion->input('medio_de_pago')),
        );

        return $this->volverAlPanel(
            'Se abrió la suscripción de '.$empresa->getAttribute('nombre').'.',
        );
    }

    public function cambiarDePlan(CambiarDePlan $peticion, Suscripcion $suscripcion): RedirectResponse
    {
        $plan = Plan::query()->findOrFail($peticion->integer('plan_id'));
        $aplicaDesde = Carbon::parse((string) $peticion->input('aplica_desde'));

        $this->suscripciones->cambiarDePlan($suscripcion, $plan, $aplicaDesde);

        return $this->volverALaFicha(
            $suscripcion,
            'El plan quedó en «'.$plan->nombre.'», y aplica desde el '
                .Formato::fechaLarga($aplicaDesde).'.',
        );
    }

    public function registrarPago(RegistrarPago $peticion, Suscripcion $suscripcion): RedirectResponse
    {
        $this->suscripciones->registrarPago(
            $suscripcion,
            $peticion->monto(),
            Carbon::parse((string) $peticion->input('fecha')),
            $peticion->numeroFactura(),
        );

        return $this->volverALaFicha(
            $suscripcion,
            Suscripciones::confirmacionDePago($suscripcion->refresh(), $this->nombreDe($suscripcion)),
        );
    }

    /**
     * Anula un pago mal registrado. La fila se queda en el historial: la regla 4
     * del proyecto no hace excepciones con la facturación.
     */
    public function anularPago(Suscripcion $suscripcion, Pago $pago): RedirectResponse
    {
        abort_unless($pago->getAttribute('suscripcion_id') === $suscripcion->getKey(), 404);

        $this->suscripciones->anularPago($pago);

        return $this->volverALaFicha(
            $suscripcion,
            'El pago quedó anulado y sigue en el historial. El próximo cobro volvió al '
                .Formato::fechaLarga($suscripcion->refresh()->proxima_fecha_cobro).'.',
        );
    }

    public function suspender(MotivoDeLaSuscripcion $peticion, Suscripcion $suscripcion): RedirectResponse
    {
        $this->suscripciones->suspender($suscripcion, $peticion->motivo());

        return $this->volverALaFicha(
            $suscripcion,
            Suscripciones::confirmacionDeSuspension($this->nombreDe($suscripcion)),
        );
    }

    public function reactivar(MotivoDeLaSuscripcion $peticion, Suscripcion $suscripcion): RedirectResponse
    {
        $this->suscripciones->reactivar($suscripcion, $peticion->motivo());

        return $this->volverALaFicha(
            $suscripcion,
            Suscripciones::confirmacionDeReactivacion($this->nombreDe($suscripcion)),
        );
    }

    /**
     * Da de alta un plan del catálogo. Es lo que hace que la tabla de precios no
     * necesite un despliegue el día que se decida (documento 6, A.1).
     */
    public function crearPlan(GuardarPlan $peticion): RedirectResponse
    {
        Plan::query()->create($peticion->plan());

        return $this->volverAlPanel('El plan quedó en el catálogo y ya se puede contratar.');
    }

    public function editarPlan(GuardarPlan $peticion, Plan $plan): RedirectResponse
    {
        $plan->forceFill($peticion->plan())->save();

        $this->suscripciones->refrescarElPlanEnLasEmpresas($plan);

        return $this->volverAlPanel(
            'El plan quedó corregido. Las empresas que lo tienen contratado pasan a facturarse '
                .'por el valor nuevo.',
        );
    }

    /**
     * Activa o desactiva un plan. **No se borra**, igual que las EPS y los
     * municipios: un plan desactivado deja de ofrecerse y sigue leyéndose en las
     * suscripciones que lo tienen contratado.
     */
    public function alternarPlan(Plan $plan): RedirectResponse
    {
        $plan->forceFill(['activo' => ! $plan->activo])->save();

        return $this->volverAlPanel($plan->activo
            ? 'El plan «'.$plan->nombre.'» vuelve a ofrecerse.'
            : 'El plan «'.$plan->nombre.'» ya no se ofrece. Quien lo tiene contratado sigue igual.');
    }

    private function nombreDe(Suscripcion $suscripcion): string
    {
        $empresa = $suscripcion->tenant()->first();

        return $empresa instanceof Tenant ? (string) $empresa->getAttribute('nombre') : 'la empresa';
    }

    private function volverAlPanel(string $estado): RedirectResponse
    {
        return redirect()
            ->route(SeccionDelPanel::Suscripciones->nombreDeRuta())
            ->with('estado', $estado);
    }

    private function volverALaFicha(Suscripcion $suscripcion, string $estado): RedirectResponse
    {
        return redirect()
            ->route('proveedor.empresas.ficha', $suscripcion->getAttribute('tenant_id'))
            ->with('estado', $estado);
    }
}
