<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Una nota de la bitácora interna sobre un cliente (documento 4, 4.3).
 *
 * «Llamadas, quejas, promesas hechas.» Es del equipo del proveedor y **no se le
 * enseña al cliente**; el porqué de que la tabla no lleve el trait ni RLS, y de
 * que el modelo esté en la lista `SIN_AUDITAR` del observador, está en su
 * migración.
 *
 * Quien la escribe es `App\Proveedor\BitacoraDelCliente`. Aquí solo viven las
 * preguntas que se le hacen a una fila.
 *
 * @property Carbon $fecha
 * @property string $texto
 */
class NotaCliente extends Model
{
    use SoftDeletes;

    protected $table = 'notas_cliente';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'fecha',
        'texto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
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
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
