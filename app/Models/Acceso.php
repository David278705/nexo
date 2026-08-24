<?php

namespace App\Models;

use App\Autenticacion\ResultadoDeAcceso;
use Database\Factories\AccesoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea del registro de accesos. Se escribe y no se toca nunca más.
 *
 * No usa `PerteneceATenant`: un intento fallido puede no tener empresa que
 * atribuir. El porqué completo está en la migración.
 *
 * @property ResultadoDeAcceso $resultado
 */
class Acceso extends Model
{
    /** @use HasFactory<AccesoFactory> */
    use HasFactory;

    /**
     * Solo hay fecha de creación. Un acceso no se actualiza jamás, así que
     * `updated_at` sería una columna que siempre repite a la otra.
     */
    public const UPDATED_AT = null;

    protected $table = 'accesos';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'tenant_id',
        'correo',
        'resultado',
        'ip',
        'agente',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resultado' => ResultadoDeAcceso::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
