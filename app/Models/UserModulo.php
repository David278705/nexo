<?php

namespace App\Models;

use App\Autorizacion\Modulo;
use App\Multiempresa\PerteneceATenant;
use Database\Factories\UserModuloFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El acceso de un usuario administrador a un módulo.
 *
 * Es tabla de negocio: lleva el trait y por tanto el filtro por empresa. Un
 * permiso concedido en una empresa no existe para ninguna otra.
 *
 * @property int $user_id
 * @property Modulo $modulo
 */
class UserModulo extends Model
{
    /** @use HasFactory<UserModuloFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    protected $table = 'user_modulos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'modulo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modulo' => Modulo::class,
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
