<?php

namespace App\Auditoria;

use App\Models\Acceso;
use App\Models\Auditoria;
use App\Models\Invitacion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

/**
 * El observador que escribe la auditoría, para todos los modelos a la vez.
 *
 * La sección 7.2 del documento de arquitectura pide un observador «para que no
 * se pueda olvidar». Un observador registrado modelo por modelo sí se puede
 * olvidar: basta con que el módulo siguiente no ponga su línea de `observe()`.
 * Por eso este se engancha a los eventos de Eloquent con comodín y **audita
 * todo lo que se guarde en la base de datos**, exista o no cuando se escribió
 * este archivo.
 *
 * Lo que no se audita es una lista corta y explícita, la de abajo. Cualquier
 * tabla nueva entra sola.
 */
class ObservadorDeAuditoria
{
    /**
     * Los modelos que no se auditan, y por qué cada uno.
     *
     * @var list<class-string<Model>>
     */
    private const SIN_AUDITAR = [
        // Auditar la auditoría es una recursión infinita.
        Auditoria::class,

        // `accesos` ya es un registro de seguridad y no se edita nunca:
        // auditarlo duplicaría cada inicio de sesión en dos tablas. Son dos
        // registros distintos a propósito y no hay que fundirlos.
        Acceso::class,

        // Una invitación guarda el hash de un token de un solo uso. Copiarlo a
        // otra tabla es repartir un secreto por más sitios sin ganar nada.
        Invitacion::class,
    ];

    /**
     * Columnas cuyo valor no se copia jamás a la auditoría.
     *
     * Se deja constancia de que el campo cambió, con un marcador en lugar del
     * valor: la auditoría tiene que poder decir «cambió la contraseña» sin
     * convertirse en el sitio más fácil del sistema para robar contraseñas.
     *
     * @var list<string>
     */
    private const OCULTAS = [
        'password',
        'remember_token',
        'token',
        'two_factor_secret',
        'two_factor_recovery_codes',

        // El dato sensible de la sección 6.3: solo lo ve quien tiene el
        // permiso del módulo de incapacidades, y la auditoría es de otro
        // módulo —tiene su propio permiso— por donde no debe asomar.
        'codigo_diagnostico',
    ];

    private const MARCADOR = '(oculto)';

    /**
     * Columnas que no cuentan como cambio en una edición.
     *
     * `updated_at` cambia siempre y no dice nada. `deleted_at` tampoco: el
     * borrado lógico y la restauración tienen su propia acción, y sin esta
     * exclusión restaurar dejaría dos líneas, una de edición y otra de
     * restauración, contando lo mismo.
     *
     * @var list<string>
     */
    private const SIN_INTERES = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * Engancha el observador a los cuatro eventos, para todos los modelos.
     *
     * Lo llama `AppServiceProvider::boot()`. Es el único sitio donde se
     * registra.
     */
    public static function registrar(): void
    {
        $observador = new self;

        $eventos = [
            'created' => AccionAuditada::Creado,
            'updated' => AccionAuditada::Editado,
            'deleted' => AccionAuditada::Eliminado,
            'restored' => AccionAuditada::Restaurado,
        ];

        foreach ($eventos as $evento => $accion) {
            Event::listen(
                "eloquent.{$evento}: *",
                /** @param array<int, mixed> $carga */
                function (string $nombre, array $carga) use ($observador, $accion): void {
                    $observador->anotar($accion, $carga[0] ?? null);
                }
            );
        }
    }

    /**
     * Escribe la línea, si el modelo es de los que se auditan.
     */
    public function anotar(AccionAuditada $accion, mixed $modelo): void
    {
        if (! $modelo instanceof Model || $this->seSalta($modelo)) {
            return;
        }

        [$anteriores, $nuevos] = $this->valores($accion, $modelo);

        // Una edición que solo tocó `updated_at` no cambió nada que a nadie le
        // importe. Se calla en lugar de llenar la tabla de líneas vacías.
        if ($accion === AccionAuditada::Editado && $nuevos === []) {
            return;
        }

        Auditoria::create([
            'tenant_id' => $this->empresaDe($modelo),
            'user_id' => Auth::id(),
            'modelo' => $modelo::class,
            'registro_id' => (string) $modelo->getKey(),
            'accion' => $accion->value,
            'valores_anteriores' => $anteriores,
            'valores_nuevos' => $nuevos,
            'ip' => request()->ip(),
            'agente' => mb_substr((string) request()->userAgent(), 0, 255) ?: null,
        ]);
    }

    private function seSalta(Model $modelo): bool
    {
        foreach (self::SIN_AUDITAR as $clase) {
            if ($modelo instanceof $clase) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los dos lados de la línea: qué había y qué quedó.
     *
     * Crear y eliminar guardan la fila entera, porque el interés está en el
     * estado completo. Editar guarda solo lo que cambió, que es lo que hay que
     * poder mostrar en pantalla sin obligar a comparar dos fotos.
     *
     * @return array{array<string, mixed>|null, array<string, mixed>|null}
     */
    private function valores(AccionAuditada $accion, Model $modelo): array
    {
        return match ($accion) {
            AccionAuditada::Creado => [null, $this->sinSecretos($modelo->getAttributes())],
            AccionAuditada::Eliminado => [$this->sinSecretos($modelo->getAttributes()), null],
            AccionAuditada::Restaurado => [null, $this->sinSecretos($modelo->getAttributes())],
            AccionAuditada::Editado => $this->cambios($modelo),
        };
    }

    /**
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function cambios(Model $modelo): array
    {
        /** @var array<string, mixed> $cambios */
        $cambios = $modelo->getChanges();

        $cambios = array_diff_key($cambios, array_flip(self::SIN_INTERES));

        /** @var array<string, mixed> $originales */
        $originales = $modelo->getOriginal();

        return [
            $this->sinSecretos(array_intersect_key($originales, $cambios)),
            $this->sinSecretos($cambios),
        ];
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private function sinSecretos(array $valores): array
    {
        foreach (self::OCULTAS as $columna) {
            if (array_key_exists($columna, $valores)) {
                $valores[$columna] = self::MARCADOR;
            }
        }

        return $valores;
    }

    /**
     * De qué empresa es la línea.
     *
     * Se toma del propio registro tocado y no de la empresa en contexto: la
     * auditoría se le muestra al cliente dueño del dato, y hay caminos donde
     * ambas no coinciden, empezando por el acceso del `owner` al entorno de un
     * cliente.
     */
    private function empresaDe(Model $modelo): ?int
    {
        if ($modelo instanceof Tenant) {
            // La empresa es el registro. Crearla o cambiarle los parámetros es
            // información de esa empresa, no de ninguna otra.
            $clave = $modelo->getKey();

            return is_numeric($clave) ? (int) $clave : null;
        }

        $empresa = $modelo->getAttribute('tenant_id');

        if (is_numeric($empresa)) {
            return (int) $empresa;
        }

        return tenant_actual_id();
    }
}
