<?php

namespace App\Models;

use Database\Factories\ErrorDeAplicacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un error de la aplicación, tal como lo captura `RegistroDeErrores` desde el
 * `reportable()` de `bootstrap/app.php` (F5-06, documento 4, capítulo 9).
 *
 * @property Carbon $ocurrido_en
 * @property string $clase
 * @property string $mensaje
 * @property string|null $archivo
 * @property int|null $linea
 * @property string|null $contexto
 */
class ErrorDeAplicacion extends Model
{
    /** @use HasFactory<ErrorDeAplicacionFactory> */
    use HasFactory;

    protected $table = 'errores_aplicacion';

    /**
     * Solo hay fecha de creación: un error ocurrido no se actualiza.
     */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'ocurrido_en',
        'clase',
        'mensaje',
        'archivo',
        'linea',
        'contexto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ocurrido_en' => 'datetime',
        ];
    }
}
