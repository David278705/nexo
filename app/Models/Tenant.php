<?php

namespace App\Models;

use App\Proveedor\EstadoDeEmpresa;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Una empresa cliente.
 *
 * No usa `PerteneceATenant`: es la raíz del aislamiento, no algo aislado.
 * Solo el rol `owner`, desde el panel del proveedor, la alcanza.
 *
 * @property EstadoDeEmpresa $estado
 * @property Carbon|null $ultimo_acceso_en
 * @property Carbon|null $ultima_novedad_en
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Los tres estados comerciales. El texto lo pone `EstadoDeEmpresa`, que es
     * donde viven además su etiqueta y su color: estas constantes siguen aquí
     * porque es como las nombra el resto del sistema desde la primera
     * migración, pero no son una segunda lista de valores posibles.
     */
    public const ESTADO_ACTIVA = EstadoDeEmpresa::Activa->value;

    public const ESTADO_SUSPENDIDA = EstadoDeEmpresa::Suspendida->value;

    public const ESTADO_EN_PRUEBA = EstadoDeEmpresa::EnPrueba->value;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'nit',
        'telefono',
        'ciudad_id',
        'estado',
        'sabado_laboral',
        'suspensiones_afectan_vacaciones',
        'perfil_sociodemografico_habilitado',
        'plan_nombre',
        'plan_empleados_incluidos',
        'plan_usuarios_incluidos',
        'plan_inicia_el',
        'certificado_camara_ruta',
        'certificado_camara_nombre',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoDeEmpresa::class,
            'sabado_laboral' => 'boolean',
            'suspensiones_afectan_vacaciones' => 'boolean',
            'perfil_sociodemografico_habilitado' => 'boolean',
            'ultimo_acceso_en' => 'datetime',
            'ultima_novedad_en' => 'datetime',
            'plan_inicia_el' => 'date',
        ];
    }

    /**
     * El municipio de la empresa, del catálogo global de F0-11.
     *
     * @return BelongsTo<Ciudad, $this>
     */
    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class);
    }

    /**
     * Si el certificado de Cámara de Comercio quedó adjunto (doc. 04, 4.2).
     *
     * Se pregunta por la ruta y no por el nombre: la ruta es lo que permite
     * recuperar el archivo, y un nombre sin archivo no sirve para nada.
     */
    public function tieneCertificadoDeCamara(): bool
    {
        return $this->getAttribute('certificado_camara_ruta') !== null;
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoDeEmpresa::Activa;
    }

    /**
     * Los inicios de sesión de esta empresa, exitosos y fallidos.
     *
     * `Acceso` no lleva `PerteneceATenant` —un intento fallido puede no tener
     * empresa a la que atribuirse—, así que quien quiera los de una empresa los
     * pide por aquí y no con un `where` a mano, igual que con los usuarios.
     *
     * @return HasMany<Acceso, $this>
     */
    public function accesos(): HasMany
    {
        return $this->hasMany(Acceso::class);
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
