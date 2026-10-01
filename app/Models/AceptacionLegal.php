<?php

namespace App\Models;

use App\Multiempresa\PerteneceATenant;
use Database\Factories\AceptacionLegalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * La constancia de que una empresa aceptó una versión de un documento legal.
 * Ver `App\Legal\AceptacionDeLaEmpresa`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $documento
 * @property string $version
 * @property string|null $ip
 * @property string|null $agente
 * @property Carbon $aceptado_en
 */
class AceptacionLegal extends Model
{
    /** @use HasFactory<AceptacionLegalFactory> */
    use HasFactory;

    use PerteneceATenant;
    use SoftDeletes;

    protected $table = 'aceptaciones_legales';

    protected $fillable = [
        'user_id',
        'documento',
        'version',
        'ip',
        'agente',
        'aceptado_en',
    ];

    protected function casts(): array
    {
        return [
            'aceptado_en' => 'datetime',
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
