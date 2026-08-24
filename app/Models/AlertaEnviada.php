<?php

namespace App\Models;

use App\Dominio\Alertas\EstadoDeAlerta;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Multiempresa\PerteneceATenant;
use Database\Factories\AlertaEnviadaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Una alerta que el motor decidió mandar (documento 2, capítulo 5).
 *
 * La fila se escribe **antes** de enviar el correo, no después: es el índice
 * único sobre `clave` el que impide el duplicado, y un índice solo puede
 * impedirlo si la fila existe antes que el envío. Por eso el estado nace
 * `pendiente`.
 *
 * El asunto y el cuerpo se guardan ya escritos. Lo que se envió no se vuelve a
 * calcular al consultarlo: el cargo o el nombre de la persona pueden haber
 * cambiado después, y entonces el registro contaría una historia distinta de la
 * que llegó al buzón.
 *
 * @property int $id
 * @property int|null $vinculacion_id
 * @property TipoDeAlerta $tipo
 * @property string $clave
 * @property Carbon $fecha_programada
 * @property Carbon $fecha_del_hecho
 * @property string $asunto
 * @property string $cuerpo
 * @property list<string> $destinatarios
 * @property EstadoDeAlerta $estado
 * @property int $intentos
 * @property string|null $motivo_del_fallo
 * @property Carbon|null $enviada_en
 * @property-read Vinculacion|null $vinculacion
 */
class AlertaEnviada extends Model
{
    /** @use HasFactory<AlertaEnviadaFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    protected $table = 'alertas_enviadas';

    /** @var list<string> */
    protected $fillable = [
        'vinculacion_id',
        'tipo',
        'clave',
        'fecha_programada',
        'fecha_del_hecho',
        'asunto',
        'cuerpo',
        'destinatarios',
        'estado',
        'intentos',
        'enviada_en',
        'motivo_del_fallo',
    ];

    /**
     * El estado no lo escribe nadie al registrar la alerta: nace pendiente por
     * este valor por defecto y de ahí solo lo mueven `marcarEnviada()` y
     * `marcarFallida()`. Está en `fillable` porque las factories arman estados
     * ya terminados, no porque haya un formulario que lo mande.
     *
     * @var array<string, string|int>
     */
    protected $attributes = [
        'estado' => EstadoDeAlerta::Pendiente->value,
        'intentos' => 0,
    ];

    /**
     * El empleado del que habla la alerta. Es nulo en los resúmenes mensuales
     * (documento 1, 9.7): esos no son de un empleado, son de la empresa.
     *
     * @return BelongsTo<Vinculacion, $this>
     */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoDeAlerta::Pendiente;
    }

    public function marcarEnviada(): void
    {
        $this->forceFill([
            'estado' => EstadoDeAlerta::Enviada,
            'enviada_en' => Carbon::now(),
            'motivo_del_fallo' => null,
        ])->save();
    }

    /**
     * Se llama cuando la cola agotó los reintentos, no en cada tropiezo.
     *
     * El motivo se guarda recortado: es para leerlo en la consola del
     * proveedor, no para reconstruir la traza.
     */
    public function marcarFallida(Throwable $error): void
    {
        $this->forceFill([
            'estado' => EstadoDeAlerta::Fallida,
            'motivo_del_fallo' => mb_substr($error->getMessage(), 0, 500),
        ])->save();
    }

    public function contarIntento(): void
    {
        $this->forceFill(['intentos' => $this->intentos + 1])->save();
    }

    /**
     * Si tiene sentido reenviarla desde la consola del proveedor (documento 4,
     * capítulo 7).
     *
     * Una pendiente ya está en camino: la cola la va a intentar sola y forzarla
     * otra vez no adelanta nada. Una enviada o una fallida sí, porque son
     * exactamente los dos casos que motivan la acción —«nunca me llegó» y «se
     * rechazó»—.
     */
    public function puedeReenviarse(): bool
    {
        return $this->estado !== EstadoDeAlerta::Pendiente;
    }

    /**
     * La deja lista para que la cola la vuelva a intentar.
     *
     * El motivo del fallo anterior se borra: si el reenvío también falla, el
     * trabajador de la cola escribe uno nuevo, y conservar el viejo contaría una
     * historia que ya no es la vigente.
     */
    public function prepararParaReenvio(): void
    {
        $this->forceFill([
            'estado' => EstadoDeAlerta::Pendiente,
            'motivo_del_fallo' => null,
        ])->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoDeAlerta::class,
            'estado' => EstadoDeAlerta::class,
            'fecha_programada' => 'date',
            'fecha_del_hecho' => 'date',
            'destinatarios' => 'array',
            'enviada_en' => 'datetime',
        ];
    }
}
