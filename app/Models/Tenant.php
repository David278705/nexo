<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una empresa cliente.
 *
 * No usa `PerteneceATenant`: es la raíz del aislamiento, no algo aislado.
 * Solo el rol `owner`, desde el panel del proveedor, la alcanza.
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_SUSPENDIDA = 'suspendida';

    public const ESTADO_EN_PRUEBA = 'en_prueba';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'nit',
        'telefono',
        'ciudad',
        'estado',
        'sabado_laboral',
        'suspensiones_afectan_vacaciones',
        'perfil_sociodemografico_habilitado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sabado_laboral' => 'boolean',
            'suspensiones_afectan_vacaciones' => 'boolean',
            'perfil_sociodemografico_habilitado' => 'boolean',
            'ultimo_acceso_en' => 'datetime',
            'ultima_novedad_en' => 'datetime',
        ];
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVA;
    }

    /**
     * Los usuarios de esta empresa.
     *
     * `User` no lleva `PerteneceATenant` a propósito —el inicio de sesión tiene
     * que encontrar a alguien por su correo antes de saber de qué empresa es—,
     * así que quien necesite los usuarios de una empresa los pide por aquí. Es
     * lo que evita el `where('tenant_id', ...)` escrito a mano que la regla de
     * aislamiento prohíbe.
     *
     * @return HasMany<User, $this>
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
