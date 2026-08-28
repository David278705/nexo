<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * La invitación de primer ingreso de un usuario.
 *
 * No usa `PerteneceATenant`: el enlace se abre sin sesión y sin empresa en
 * contexto. El porqué completo está en la migración.
 *
 * Lleva `SoftDeletes` desde REV-10, la misma deuda que pagan `User` y
 * `Acceso`: nada la borra hoy, pero la tabla no debe ser una excepción no
 * declarada a la regla 4 del proyecto.
 *
 * @property Carbon $expira_en
 * @property Carbon|null $aceptada_en
 */
class Invitacion extends Model
{
    use SoftDeletes;

    protected $table = 'invitaciones';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'token',
        'expira_en',
        'aceptada_en',
    ];

    /**
     * El token va oculto por si alguna vez este modelo termina en una prop de
     * Inertia. Aunque esté con hash, no tiene por qué salir a la pantalla.
     *
     * @var list<string>
     */
    protected $hidden = ['token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'aceptada_en' => 'datetime',
        ];
    }

    public function estaVigente(): bool
    {
        return $this->aceptada_en === null && $this->expira_en->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
