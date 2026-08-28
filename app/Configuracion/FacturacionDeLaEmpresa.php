<?php

namespace App\Configuracion;

use App\Models\Pago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Presentacion\Formato;

/**
 * La pestaña «Facturación» de Configuración (documento 3, 5.7): «Plan
 * contratado, número de empleados y usuarios incluidos, historial de pagos y
 * medio de pago. Solo el superadministrador.»
 *
 * Es la misma información que `App\Proveedor\PanelDeSuscripciones` y
 * `App\Proveedor\FichaDeEmpresa::suscripcion()` ya calculan y ya prueban del
 * lado del proveedor —F5-04—, leída aquí de solo lectura y acotada a la
 * empresa en contexto. **No hay ninguna acción**: registrar un pago, cambiar
 * de plan, suspender y reactivar son decisiones del proveedor sobre su
 * cliente (documento 4, 4.3), no del cliente sobre sí mismo.
 *
 * «Solo el superadministrador» ya lo impone el propio módulo `Configuracion`
 * —`Modulo::esSoloDelSuperadministrador()`, aplicado por
 * `PoliticaDeModulos` antes de que la petición llegue aquí—, así que esta
 * clase no repite el candado.
 */
class FacturacionDeLaEmpresa
{
    private function __construct(private readonly Tenant $empresa) {}

    public static function de(Tenant $empresa): self
    {
        return new self($empresa);
    }

    /**
     * Todas las props de la pestaña.
     *
     * @return array<string, mixed>
     */
    public function paraLaPantalla(): array
    {
        return ['facturacion' => $this->datos()];
    }

    /**
     * Una empresa sin suscripción no enseña un hueco: dice que no tiene plan
     * contratado todavía. Es el caso de las que están en prueba y de las que
     * se crearon antes de que existiera el catálogo de planes (A.1).
     *
     * @return array<string, mixed>
     */
    private function datos(): array
    {
        $suscripcion = $this->empresa->suscripcion()->first();

        if (! $suscripcion instanceof Suscripcion) {
            return [
                'existe' => false,
                'titulo' => 'Todavía no hay un plan contratado',
                'descripcion' => 'Tu empresa no tiene una suscripción activa en este momento. '
                    .'Para contratar un plan o resolver una duda de facturación, comunícate con '
                    .'tu proveedor.',
            ];
        }

        $plan = $suscripcion->plan()->first();
        $estado = $suscripcion->estado;

        return [
            'existe' => true,
            'plan' => $plan instanceof Plan ? $plan->nombre : Formato::VACIO,
            'estado' => $estado->etiqueta(),
            'estadoTono' => $estado->tono(),
            'datos' => [
                [
                    'etiqueta' => 'Empleados incluidos',
                    'valor' => $plan instanceof Plan ? (string) $plan->empleados_incluidos : Formato::VACIO,
                ],
                [
                    'etiqueta' => 'Usuarios incluidos',
                    'valor' => $plan instanceof Plan ? (string) $plan->usuarios_incluidos : Formato::VACIO,
                ],
                ['etiqueta' => 'Valor mensual', 'valor' => Formato::dinero($suscripcion->valorMensual())],
                [
                    'etiqueta' => 'Próximo cobro',
                    'valor' => Formato::fechaLarga($suscripcion->proxima_fecha_cobro),
                ],
                ['etiqueta' => 'Medio de pago', 'valor' => $suscripcion->medio_de_pago->etiqueta()],
            ],
            'historial' => $this->historialDePagos($suscripcion),
        ];
    }

    /**
     * El historial de pagos, de la más reciente a la más antigua. Misma forma
     * que `FichaDeEmpresa::historialDePagos()` del lado del proveedor, sin la
     * bandera `anulada` que allí decide si el botón de anular se deshabilita:
     * aquí no hay ninguna acción sobre la fila, solo su estado.
     *
     * @return list<array<string, mixed>>
     */
    private function historialDePagos(Suscripcion $suscripcion): array
    {
        return $suscripcion->pagos()
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Pago $pago): array => [
                'id' => $pago->getKey(),
                'fecha' => Formato::fecha($pago->fecha),
                'monto' => Formato::dinero($pago->monto),
                'numeroFactura' => $pago->numero_factura ?? Formato::VACIO,
                'estado' => $pago->estado->etiqueta(),
                'tono' => $pago->estado->tono(),
            ])
            ->values()
            ->all();
    }
}
