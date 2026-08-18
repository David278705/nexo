<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Notificaciones\RestablecerContrasena;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * @property int|null $tenant_id
 * @property Rol $rol
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'rol',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => Rol::class,
        ];
    }

    public function esOwner(): bool
    {
        return $this->rol === Rol::Owner;
    }

    public function esSuperadministrador(): bool
    {
        return $this->rol === Rol::Superadministrador;
    }

    public function esAdministrador(): bool
    {
        return $this->rol === Rol::Administrador;
    }

    /**
     * Los módulos asignados a este usuario.
     *
     * Solo tienen sentido para el rol `admin`: el superadministrador los tiene
     * todos por su rol y el `owner` no tiene ninguno del entorno del cliente.
     *
     * @return HasMany<UserModulo, $this>
     */
    public function modulos(): HasMany
    {
        return $this->hasMany(UserModulo::class, 'user_id');
    }

    /**
     * Si este módulo concreto figura entre los asignados.
     *
     * No decide el acceso: eso lo hace `PoliticaDeModulos`, que además tiene en
     * cuenta el rol. Aquí solo se responde qué hay en la tabla.
     *
     * Se lee de la relación cargada, no con una consulta por pregunta: una
     * misma petición pregunta por varios módulos al pintar el menú.
     */
    public function tieneModuloAsignado(Modulo $modulo): bool
    {
        return $this->modulosAsignados()->contains($modulo);
    }

    /**
     * @return Collection<int, Modulo>
     */
    public function modulosAsignados(): Collection
    {
        return $this->modulos->map(fn (UserModulo $asignado) => $asignado->modulo);
    }

    /**
     * Las invitaciones de primer ingreso emitidas a este usuario.
     *
     * @return HasMany<Invitacion, $this>
     */
    public function invitaciones(): HasMany
    {
        return $this->hasMany(Invitacion::class, 'user_id');
    }

    /**
     * El correo de restablecimiento, en español y con su caducidad dicha.
     *
     * Laravel manda el suyo si no se cambia esto; el sistema entero está en
     * español, así que el único correo en inglés sería justo el que más gente
     * ve.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new RestablecerContrasena($token));
    }

    /**
     * La empresa a la que pertenece. Nula solo para el rol `owner`, que vive
     * en el panel del proveedor y no es de ninguna empresa.
     *
     * El usuario **no** usa `PerteneceATenant`: hay que poder encontrarlo por
     * su correo antes de saber de qué empresa es, que es justo lo que hace el
     * inicio de sesión.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
