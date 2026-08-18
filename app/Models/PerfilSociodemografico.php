<?php

namespace App\Models;

use App\Contratacion\TipoVivienda;
use App\Multiempresa\PerteneceATenant;
use Database\Factories\PerfilSociodemograficoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El bloque opcional de la sección 5.2 del documento 1.
 *
 * Cuelga de la persona, no de la vinculación: el estrato o el nivel educativo
 * no cambian porque a alguien lo recontraten.
 *
 * **Todos sus campos son opcionales aunque la empresa tenga el bloque
 * activo.** Un perfil completamente vacío es un registro válido, y una persona
 * sin perfil también: si la empresa activa el bloque cuando ya tiene su planta
 * cargada, los empleados existentes quedan en blanco y eso no es un error.
 *
 * @property-read Persona $persona
 */
class PerfilSociodemografico extends Model
{
    /** @use HasFactory<PerfilSociodemograficoFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    protected $table = 'perfil_sociodemografico';

    /** @var list<string> */
    protected $fillable = [
        'persona_id',
        'estado_civil',
        'nivel_educativo',
        'numero_hijos',
        'personas_a_cargo',
        'tipo_vivienda',
        'estrato',
        'medio_transporte',
        'tiempo_desplazamiento',
    ];

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Si el perfil está sin llenar del todo.
     *
     * Lo usa la ficha del empleado para mostrar el bloque como pendiente en vez
     * de como una lista de campos vacíos.
     */
    public function estaVacio(): bool
    {
        foreach ($this->getFillable() as $campo) {
            if ($campo !== 'persona_id' && $this->getAttribute($campo) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_vivienda' => TipoVivienda::class,
            'numero_hijos' => 'integer',
            'personas_a_cargo' => 'integer',
            'estrato' => 'integer',
        ];
    }
}
