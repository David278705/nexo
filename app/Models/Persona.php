<?php

namespace App\Models;

use App\Contratacion\Genero;
use App\Contratacion\TipoDocumento;
use App\Multiempresa\PerteneceATenant;
use Database\Factories\PersonaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El ser humano, no su contrato.
 *
 * Una persona existe con independencia de estar contratada, y puede tener
 * varias vinculaciones a lo largo del tiempo sin que sus datos personales se
 * dupliquen ni una sola vez. Es la regla 5 del proyecto y la sección 3.2 del
 * documento de arquitectura.
 *
 * Lo que se registra al contratar a alguien es siempre lo mismo: se busca la
 * persona por su documento; si existe, se reutiliza y se le crea una
 * vinculación nueva —y ahí es donde aparecerá la advertencia de reingreso de
 * F1-05—; si no existe, se crean las dos cosas.
 *
 * @property int $id
 * @property string $numero_documento
 * @property-read Collection<int, Vinculacion> $vinculaciones
 * @property-read PerfilSociodemografico|null $perfil
 */
class Persona extends Model
{
    /** @use HasFactory<PersonaFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'fecha_expedicion_documento',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'fecha_nacimiento',
        'genero',
        'pais_nacimiento',
        'ciudad_nacimiento_id',
        'departamento_nacimiento_exterior',
        'ciudad_nacimiento_exterior',
        'pais_expedicion',
        'ciudad_expedicion_id',
        'departamento_expedicion_exterior',
        'ciudad_expedicion_exterior',
        'direccion',
        'ciudad_residencia_id',
        'celular',
        'correo',
    ];

    /**
     * Las vinculaciones de la persona, de la más reciente a la más antigua.
     *
     * Son varias a propósito: cada contratación es un registro independiente.
     *
     * @return HasMany<Vinculacion, $this>
     */
    public function vinculaciones(): HasMany
    {
        return $this->hasMany(Vinculacion::class)->orderByDesc('fecha_ingreso');
    }

    /**
     * @return HasOne<PerfilSociodemografico, $this>
     */
    public function perfil(): HasOne
    {
        return $this->hasOne(PerfilSociodemografico::class);
    }

    /**
     * El país de nacimiento y el de expedición del documento.
     *
     * La columna guarda el código ISO —`CO`, `VE`— y no el nombre, porque de él
     * depende la regla 5.1.1: dentro de Colombia el departamento y la ciudad
     * salen de listas encadenadas; fuera se digitan a mano. Estas relaciones
     * son para enseñar el nombre en la ficha, no para decidir esa regla: para
     * eso se compara contra `Pais::COLOMBIA`.
     *
     * @return BelongsTo<Pais, $this>
     */
    public function paisNacimiento(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_nacimiento', 'codigo');
    }

    /**
     * @return BelongsTo<Pais, $this>
     */
    public function paisExpedicion(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_expedicion', 'codigo');
    }

    /**
     * @return BelongsTo<Ciudad, $this>
     */
    public function ciudadResidencia(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_residencia_id');
    }

    /**
     * @return BelongsTo<Ciudad, $this>
     */
    public function ciudadNacimiento(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_nacimiento_id');
    }

    /**
     * @return BelongsTo<Ciudad, $this>
     */
    public function ciudadExpedicion(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_expedicion_id');
    }

    /**
     * El nombre como se muestra en pantalla: los cuatro pedazos que existan,
     * separados por un espacio.
     *
     * El segundo nombre y el segundo apellido son opcionales porque hay
     * personas que no los tienen, y concatenarlos sin filtrar deja dobles
     * espacios en media aplicación.
     */
    public function nombreCompleto(): string
    {
        $partes = [
            $this->getAttribute('primer_nombre'),
            $this->getAttribute('segundo_nombre'),
            $this->getAttribute('primer_apellido'),
            $this->getAttribute('segundo_apellido'),
        ];

        return implode(' ', array_filter(
            array_map(fn (mixed $parte): string => trim((string) $parte), $partes),
            fn (string $parte): bool => $parte !== '',
        ));
    }

    /**
     * Busca una persona por su documento dentro de la empresa en contexto.
     *
     * Es el primer paso de todo registro de empleado y el que decide si hay que
     * crear una persona o reutilizar la que ya existe. Vive aquí y no en el
     * controlador porque lo van a usar el formulario, la advertencia de
     * reingreso y la carga inicial, y los tres tienen que buscar igual.
     *
     * No lleva `where('tenant_id', ...)`: lo pone el trait. Escribirlo a mano
     * aquí sería la señal de que el trait falta.
     *
     * @param  Builder<$this>  $consulta
     * @return Builder<$this>
     */
    public function scopeConDocumento(Builder $consulta, string $numeroDeDocumento): Builder
    {
        return $consulta->where('numero_documento', $numeroDeDocumento);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'genero' => Genero::class,
            'fecha_nacimiento' => 'date',
            'fecha_expedicion_documento' => 'date',
        ];
    }
}
