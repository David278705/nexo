<?php

namespace App\Models;

use App\Contratacion\EventoContractual;
use App\Contratacion\TipoContrato;
use App\Multiempresa\PerteneceATenant;
use Database\Factories\PeriodoContractualFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Un tramo de la vida del contrato: el original o cada movimiento posterior.
 *
 * El primero —`orden = 1`, evento `inicial`— es el contrato original y lo abre
 * sola la vinculación al crearse; nadie tiene que acordarse de escribirlo. Los
 * siguientes los agrega `Vinculacion::registrarPeriodo()`, que es por donde
 * pasarán «Renovar» y «Cambiar a término indefinido» (F1-09).
 *
 * Este modelo no calcula nada. Las prórrogas acumuladas, la duración total del
 * vínculo y la fecha de vencimiento vigente se calculan sobre el historial
 * completo, y de eso se ocupa `App\Contratacion\HistorialDeContrato`.
 *
 * @property int $id
 * @property int $orden
 * @property EventoContractual $evento
 * @property TipoContrato $tipo_contrato
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property-read Vinculacion $vinculacion
 */
class PeriodoContractual extends Model
{
    /** @use HasFactory<PeriodoContractualFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    protected $table = 'periodos_contractuales';

    /** @var list<string> */
    protected $fillable = [
        'vinculacion_id',
        'orden',
        'evento',
        'tipo_contrato',
        'fecha_inicio',
        'fecha_fin',
    ];

    /**
     * @return BelongsTo<Vinculacion, $this>
     */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }

    public function esInicial(): bool
    {
        return $this->evento->esInicial();
    }

    public function esProrroga(): bool
    {
        return $this->evento->esProrroga();
    }

    /**
     * Si el tramo dura menos de un año.
     *
     * Es la única condición que define una «prórroga corta», que es la que
     * cuenta para el límite del artículo 46 del CST: «la renovación no podrá
     * ser inferior a un año». Un tramo del 15 de abril de 2026 al 14 de abril
     * de 2027 dura exactamente un año contando los dos extremos, así que no es
     * corto; un día menos sí lo es.
     *
     * Un tramo sin fecha de finalización nunca es corto: no termina por plazo.
     */
    public function esInferiorAUnAnio(): bool
    {
        $fin = $this->getAttribute('fecha_fin');

        if (! $fin instanceof Carbon) {
            return false;
        }

        return $fin->lessThan($this->fecha_inicio->copy()->addYear()->subDay());
    }

    /**
     * Del primer tramo al último, que es como se lee un historial.
     *
     * Se ordena por `orden` y no por fecha: dos tramos pueden empezar el mismo
     * día —un cambio a indefinido registrado el día en que vencía la prórroga—
     * y ahí la fecha no desempata.
     *
     * @param  Builder<$this>  $consulta
     * @return Builder<$this>
     */
    public function scopeEnOrden(Builder $consulta): Builder
    {
        return $consulta->orderBy('orden');
    }

    /**
     * @param  Builder<$this>  $consulta
     * @return Builder<$this>
     */
    public function scopeProrrogas(Builder $consulta): Builder
    {
        return $consulta->whereIn('evento', [
            EventoContractual::ProrrogaPactada->value,
            EventoContractual::ProrrogaAutomatica->value,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'evento' => EventoContractual::class,
            'tipo_contrato' => TipoContrato::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }
}
