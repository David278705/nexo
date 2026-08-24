<?php

namespace App\Models;

use App\Proveedor\EstadoDeCobro;
use App\Proveedor\EstadoDeSuscripcion;
use App\Proveedor\MedioDePago;
use Database\Factories\SuscripcionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * La suscripción de una empresa cliente (documento 4, capítulo 6).
 *
 * No usa `PerteneceATenant` ni tiene RLS, y el porqué está en su migración: es
 * una tabla del panel del proveedor, se lee con el `owner` —que no tiene empresa
 * en contexto— y ninguna pantalla del cliente la muestra.
 *
 * Quien la escribe es `App\Proveedor\Suscripciones`. Aquí solo viven las
 * preguntas que se le hacen a una fila.
 *
 * @property EstadoDeSuscripcion $estado
 * @property MedioDePago $medio_de_pago
 * @property Carbon $inicia_el
 * @property Carbon $plan_aplica_desde
 * @property Carbon $proxima_fecha_cobro
 * @property Carbon|null $suspendida_en
 * @property Carbon|null $reactivada_en
 * @property string|null $motivo_de_suspension
 * @property Plan|null $plan
 * @property Tenant|null $tenant
 */
class Suscripcion extends Model
{
    /** @use HasFactory<SuscripcionFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'suscripciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'inicia_el',
        'plan_aplica_desde',
        'proxima_fecha_cobro',
        'medio_de_pago',
        'estado',
        'suspendida_en',
        'motivo_de_suspension',
        'reactivada_en',
        'motivo_de_reactivacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoDeSuscripcion::class,
            'medio_de_pago' => MedioDePago::class,
            'inicia_el' => 'date',
            'plan_aplica_desde' => 'date',
            'proxima_fecha_cobro' => 'date',
            'suspendida_en' => 'datetime',
            'reactivada_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * El historial de facturas y pagos, de la más reciente a la más antigua.
     *
     * @return HasMany<Pago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function estaSuspendida(): bool
    {
        return $this->estado->estaSuspendida();
    }

    /**
     * En qué franja de cobro está: al día, por vencer o en mora.
     */
    public function cobro(): EstadoDeCobro
    {
        return EstadoDeCobro::segunElProximoCobro($this->proxima_fecha_cobro);
    }

    /**
     * Cuántos días de atraso lleva. Cero si el cobro todavía no ha vencido.
     */
    public function diasDeAtraso(): int
    {
        return EstadoDeCobro::diasDeAtraso($this->proxima_fecha_cobro);
    }

    /**
     * Cómo se lee el estado de cobro en la celda: «Al día», «Vence en 3 días»,
     * «Mora de 22 días».
     */
    public function textoDelCobro(): string
    {
        return EstadoDeCobro::texto($this->proxima_fecha_cobro);
    }

    /**
     * El valor mensual que se le factura, que es el precio del plan que tiene
     * contratado hoy. **No se guarda una copia en la suscripción**: sería un
     * segundo precio que se desalinea el día que el plan cambie de valor, y el
     * precio de lo ya facturado está en cada fila de `pagos`.
     */
    public function valorMensual(): float
    {
        // Como propiedad y no con `->first()`: quien recorra muchas
        // suscripciones las trae con `with('plan')` y así no paga una consulta
        // por fila.
        $plan = $this->plan;

        return $plan instanceof Plan ? (float) $plan->precio_mensual : 0.0;
    }
}
