<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
