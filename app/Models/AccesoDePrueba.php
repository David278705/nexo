<?php

namespace App\Models;

use Database\Factories\AccesoDePruebaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un acceso de prueba con vigencia limitada al ambiente de demostración
 * (documento 4, capítulo 10 y 12.1). Lo emite y lo lee `App\Proveedor\AccesosDePrueba`.
 *
 * @property Carbon $expira_en
 * @property Carbon|null $revocado_en
 */
class AccesoDePrueba extends Model
{
    /** @use HasFactory<AccesoDePruebaFactory> */
    use HasFactory;

    protected $table = 'accesos_de_prueba';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'nombre_prospecto',
        'token',
        'dias_de_vigencia',
        'generado_en',
        'expira_en',
        'ultimo_uso_en',
        'revocado_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generado_en' => 'datetime',
            'expira_en' => 'datetime',
            'ultimo_uso_en' => 'datetime',
            'revocado_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Si todavía sirve para entrar: ni revocado, ni vencido.
     */
    public function estaVigente(): bool
    {
        return $this->revocado_en === null && $this->expira_en->isFuture();
    }
}
