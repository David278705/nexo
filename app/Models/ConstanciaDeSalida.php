<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * La constancia de que a una empresa se le entregó su información, o de que se
 * eliminó (`PROD-15`, documento 6, A.4, pasos 1 y 4).
 *
 * Es del proveedor, como `Suscripcion`: no lleva el trait `PerteneceATenant`
 * porque la escribe el `owner` desde el panel, sin empresa en contexto, y se
 * conserva aunque los datos del cliente ya no existan. Se escribe una vez y no
 * se edita.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $tipo
 * @property Carbon $ocurrida_en
 * @property string|null $registrada_por
 * @property array<string, int> $detalle
 * @property string|null $huella_sha256
 */
class ConstanciaDeSalida extends Model
{
    use SoftDeletes;

    public const ENTREGA = 'entrega';

    public const ELIMINACION = 'eliminacion';

    protected $table = 'constancias_de_salida';

    protected $fillable = [
        'tenant_id',
        'tipo',
        'ocurrida_en',
        'registrada_por',
        'detalle',
        'huella_sha256',
    ];

    protected function casts(): array
    {
        return [
            'ocurrida_en' => 'datetime',
            'detalle' => 'array',
        ];
    }
}
