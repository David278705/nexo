<?php

namespace App\Models;

use App\Dominio\Calculo\MotorDeCalculo;
use App\Multiempresa\PerteneceATenant;
use App\Novedades\ConfirmacionDeBorrado;
use App\Novedades\NovedadEditable;
use App\Presentacion\Formato;
use Database\Factories\VacacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Un periodo de vacaciones disfrutado por un empleado (documento 1, 6.4).
 *
 * Cuelga de la vinculación, no de la persona: es la regla 5 del proyecto,
 * igual que las incapacidades.
 *
 * **No hay columna de días.** Se cuentan en días hábiles con
 * `dias()`, que llama al motor cada vez, nunca una columna que pueda quedar
 * desincronizada de `fecha_inicio` y `fecha_fin` si alguien las corrige
 * (F2-10). `SaldoDeVacaciones::al()` resta esos días del saldo: el borrado
 * lógico de una fila lo restituye solo, sin operación inversa.
 *
 * @property int $id
 * @property int $vinculacion_id
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property-read Vinculacion $vinculacion
 */
class Vacacion extends Model implements NovedadEditable
{
    /** @use HasFactory<VacacionFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    protected $table = 'vacaciones';

    /** @var list<string> */
    protected $fillable = [
        'vinculacion_id',
        'fecha_inicio',
        'fecha_fin',
    ];

    /**
     * @return BelongsTo<Vinculacion, $this>
     */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }

    /**
     * Los días hábiles realmente disfrutados de este periodo (documento 1,
     * 6.4): domingos y festivos no cuentan, el sábado cuenta o no según el
     * parámetro de la empresa dueña de la vinculación, y **los días que caen
     * dentro de una incapacidad ya registrada no cuentan tampoco** (`HD-04`,
     * decisión D.7): sobre una incapacidad no se disfrutan vacaciones, así
     * que esos días vuelven al saldo solos, sin ninguna operación inversa.
     *
     * Las fechas pedidas —`fecha_inicio` y `fecha_fin`— no se tocan: son las
     * que la persona pidió. `notaDeTraslape()` es quien explica por qué los
     * días son menos que el rango pedido.
     */
    public function dias(): int
    {
        return $this->diasSolicitados() - $this->diasInterrumpidosPorIncapacidad();
    }

    /**
     * Los días hábiles del rango tal como se pidió, sin descontar ninguna
     * incapacidad. Es lo que `dias()` devolvía antes de `HD-04` y sigue
     * siendo la base sobre la que se resta el traslape.
     */
    private function diasSolicitados(): int
    {
        return MotorDeCalculo::diasHabilesVacaciones(
            $this->fecha_inicio,
            $this->fecha_fin,
            $this->vinculacion->tenant,
        );
    }

    /**
     * Los días hábiles de este periodo que una incapacidad ya registrada se
     * comió, sumados incapacidad por incapacidad.
     *
     * Se pueden sumar sin doble conteo porque las incapacidades nunca se
     * pisan entre sí (`HD-03`): sus rangos dentro de este periodo son
     * disjuntos. El parámetro `incapacidad_interrumpe_vacaciones` está
     * decidido y no es de empresa (D.7), pero vive en configuración como
     * todas las constantes legales del motor, no suelto en la lógica.
     */
    private function diasInterrumpidosPorIncapacidad(): int
    {
        if (config('reglas_laborales.incapacidad_interrumpe_vacaciones') !== true) {
            return 0;
        }

        return $this->incapacidadesQueLaTraslapan()
            ->sum(fn (Incapacidad $incapacidad): int => MotorDeCalculo::diasHabilesVacaciones(
                $this->fecha_inicio->max($incapacidad->fecha_inicio),
                $this->fecha_fin->min($incapacidad->fecha_fin),
                $this->vinculacion->tenant,
            ));
    }

    /**
     * La nota visible que explica el traslape con una incapacidad (`HD-04`),
     * o cadena vacía si este periodo no tiene ninguno. La pintan la ficha del
     * empleado y el listado del módulo.
     */
    public function notaDeTraslape(): string
    {
        $incapacidades = $this->incapacidadesQueLaTraslapan();

        if ($incapacidades->isEmpty()) {
            return '';
        }

        $periodos = $incapacidades
            ->map(fn (Incapacidad $incapacidad): string => Formato::periodo($incapacidad->fecha_inicio, $incapacidad->fecha_fin))
            ->implode(' y ');

        $sujeto = $incapacidades->count() === 1 ? 'La incapacidad' : 'Las incapacidades';
        $verbo = $incapacidades->count() === 1 ? 'interrumpió' : 'interrumpieron';

        return $sujeto.' '.$periodos.' '.$verbo.' el disfrute: '
            .Formato::dias($this->diasInterrumpidosPorIncapacidad()).' volvieron al saldo.';
    }

    /**
     * Las incapacidades de esta vinculación que caen sobre este periodo,
     * ordenadas por fecha. Se filtra la colección ya cargada de la
     * vinculación y no una consulta aparte: `FichaDeEmpleado` y el listado ya
     * la traen con `with()`, y una consulta por cada fila sería un traslape
     * por otra novedad —esta vez de rendimiento—.
     *
     * @return Collection<int, Incapacidad>
     */
    private function incapacidadesQueLaTraslapan(): Collection
    {
        return $this->vinculacion->incapacidades
            ->filter(fn (Incapacidad $incapacidad): bool => $incapacidad->fecha_inicio->lessThanOrEqualTo($this->fecha_fin)
                && $incapacidad->fecha_fin->greaterThanOrEqualTo($this->fecha_inicio))
            ->sortBy(fn (Incapacidad $incapacidad): int => $incapacidad->fecha_inicio->getTimestamp())
            ->values();
    }

    public function vinculacionDeLaNovedad(): Vinculacion
    {
        return $this->vinculacion;
    }

    /**
     * @return array<string, string|bool|int|null>
     */
    public function valoresParaEdicion(): array
    {
        return [
            'vinculacion_id' => $this->vinculacion_id,
            'fecha_inicio' => $this->fecha_inicio->toDateString(),
            'fecha_fin' => $this->fecha_fin->toDateString(),
        ];
    }

    /**
     * Vacío: los dos campos de solo lectura del formulario —los días hábiles y
     * la franja de saldo— los calcula el servidor con lo que se está digitando,
     * no con lo que hay guardado.
     *
     * @return array<string, string>
     */
    public function contextoDeEdicion(): array
    {
        return [];
    }

    public function confirmacionDeBorrado(): string
    {
        return ConfirmacionDeBorrado::deVacacion($this);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }
}
