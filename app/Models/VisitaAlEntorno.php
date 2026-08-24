<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una entrada del proveedor al entorno de un cliente (documento 4, 4.4).
 *
 * La fila es la constancia y no un permiso: mientras `terminada_en` esté vacía,
 * `App\Proveedor\AccesoAlEntorno` la considera en curso y `ResolverTenant` pone
 * al `owner` dentro de esa empresa. Cerrarla es lo único que la edita.
 *
 * @property int $tenant_id
 * @property int $user_id
 * @property string $motivo
 * @property Carbon|null $terminada_en
 * @property Carbon|null $escritura_activada_en
 * @property string|null $motivo_de_escritura
 * @property Carbon $created_at
 */
class VisitaAlEntorno extends Model
{
    protected $table = 'visitas_al_entorno';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'motivo',
        'terminada_en',
        'escritura_activada_en',
        'motivo_de_escritura',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'terminada_en' => 'datetime',
            'escritura_activada_en' => 'datetime',
        ];
    }

    public function estaEnCurso(): bool
    {
        return $this->terminada_en === null;
    }

    /**
     * Si en esta visita se puede además escribir.
     *
     * Es «solo lectura por defecto» dicho al revés: lo que abre la escritura es
     * una fecha puesta expresamente, no la ausencia de una prohibición. Una
     * visita ya cerrada no permite nada, aunque la escritura se hubiera
     * activado en su momento.
     */
    public function permiteEscribir(): bool
    {
        return $this->estaEnCurso() && $this->escritura_activada_en !== null;
    }

    /**
     * Cuánto duró, en minutos, o null si sigue abierta.
     */
    public function minutos(): ?int
    {
        return $this->terminada_en === null
            ? null
            : (int) $this->created_at->diffInMinutes($this->terminada_en);
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
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
