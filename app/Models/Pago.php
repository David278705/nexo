<?php

namespace App\Models;

use App\Proveedor\EstadoDeFactura;
use Database\Factories\PagoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Una factura de suscripción y su suerte (documento 4, capítulo 6 y 17.2).
 *
 * Cuelga de la suscripción, no de la empresa: la empresa se llega por la
 * suscripción y así un pago no puede apuntar a dos sitios distintos.
 *
 * @property EstadoDeFactura $estado
 * @property Carbon $fecha
 * @property string $monto
 * @property string|null $numero_factura
 */
class Pago extends Model
{
    /** @use HasFactory<PagoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'pagos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'suscripcion_id',
        'monto',
        'fecha',
        'estado',
        'numero_factura',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoDeFactura::class,
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Suscripcion, $this>
     */
    public function suscripcion(): BelongsTo
    {
        return $this->belongsTo(Suscripcion::class);
    }
}
