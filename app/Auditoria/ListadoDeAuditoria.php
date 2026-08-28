<?php

namespace App\Auditoria;

use App\Listados\Listado;
use App\Models\Auditoria;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * La pantalla de auditoría (REV-04, documento 1, 8.2 y documento 3, 5.6): de
 * solo lectura, como alertas, con sus cuatro filtros —rango de fechas,
 * usuario, módulo y acción— y el detalle de una fila con lo que había y lo
 * que quedó.
 *
 * La consulta parte de `Auditoria::deLaEmpresaActual()`: la empresa en
 * contexto es la única que un usuario del entorno del cliente puede ver, y
 * ese filtro lo pone el propio modelo (documento 2, 7.2), no esta clase.
 *
 * Lo que llegó oculto a la tabla —la contraseña, el código de diagnóstico—
 * ya llegó así desde `ObservadorDeAuditoria::sinSecretos()`: esta pantalla no
 * vuelve a decidir qué esconder, solo enseña lo que hay.
 *
 * @extends Listado<Auditoria>
 */
class ListadoDeAuditoria extends Listado
{
    /**
     * Campos que no se enseñan en el detalle: son el identificador de la fila
     * y la empresa dueña, implícita en la propia pantalla.
     *
     * @var list<string>
     */
    private const CAMPOS_SIN_INTERES = ['id', 'tenant_id'];

    protected function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        public readonly string $usuario,
        public readonly string $modulo,
        public readonly string $accion,
        string $orden,
        string $direccion,
        int $porPagina,
    ) {
        parent::__construct($orden, $direccion, $porPagina);
    }

    public static function desde(Request $peticion): self
    {
        return new self(
            desde: self::fecha($peticion, 'desde'),
            hasta: self::fecha($peticion, 'hasta'),
            usuario: self::filtro($peticion, 'usuario', self::idsDeUsuarios()),
            modulo: self::filtro($peticion, 'modulo', ModuloDelRegistro::claves()),
            accion: self::filtro($peticion, 'accion', self::valoresDeAccion()),
            orden: self::ordenPedido($peticion),
            direccion: self::direccionPedida($peticion),
            porPagina: self::porPagina($peticion),
        );
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function usuarios(): array
    {
        // Con `withTrashed()`: un usuario desactivado sigue teniendo líneas
        // en la auditoría, y el filtro tiene que poder seguir encontrándolas.
        return User::query()
            ->withTrashed()
            ->where('tenant_id', tenant_actual_id())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $usuario): array => ['valor' => (string) $usuario->getKey(), 'texto' => $usuario->name])
            ->all();
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function modulos(): array
    {
        return ModuloDelRegistro::opciones();
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function acciones(): array
    {
        return array_map(
            fn (AccionAuditada $accion): array => ['valor' => $accion->value, 'texto' => $accion->etiqueta()],
            AccionAuditada::cases(),
        );
    }

    /**
     * Una fila de la tabla, con su detalle dentro: el mismo criterio que
     * `ListadoDeAlertas`, para no pedir nada aparte al abrir el panel lateral.
     *
     * @return array<string, mixed>
     */
    public static function fila(Auditoria $linea): array
    {
        $cambios = self::cambios($linea);

        return [
            'id' => (int) $linea->getKey(),
            'fecha' => Formato::fechaYHora($linea->created_at),
            'usuario' => $linea->user_id === null ? 'Sistema' : $linea->usuario->name,
            'modulo' => ModuloDelRegistro::etiqueta($linea->modelo),
            'accion' => $linea->accion->etiqueta(),
            'tono' => $linea->accion->tono(),
            'registro' => self::etiquetaDelRegistro($linea),
            'detalle' => self::detalle($linea->accion, $cambios),
            'cambios' => $cambios,
        ];
    }

    /**
     * @return Builder<Auditoria>
     */
    protected function base(): Builder
    {
        return Auditoria::query()->deLaEmpresaActual()->with('usuario');
    }

    /**
     * @param  Builder<Auditoria>  $consulta
     */
    protected function filtrar(Builder $consulta): void
    {
        if ($this->desde !== '') {
            $consulta->whereDate('created_at', '>=', $this->desde);
        }

        if ($this->hasta !== '') {
            $consulta->whereDate('created_at', '<=', $this->hasta);
        }

        if ($this->usuario !== self::TODOS) {
            $consulta->where('user_id', $this->usuario);
        }

        if ($this->modulo !== self::TODOS) {
            $consulta->whereIn('modelo', ModuloDelRegistro::modelosDe($this->modulo));
        }

        if ($this->accion !== self::TODOS) {
            $consulta->where('accion', $this->accion);
        }
    }

    /**
     * @return array<string, callable(Builder<Auditoria>, string): mixed>
     */
    protected static function columnasDeOrden(): array
    {
        return [
            'fecha' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('created_at', $sentido),
        ];
    }

    protected static function ordenPorDefecto(): string
    {
        return 'fecha';
    }

    /**
     * Abre por lo más reciente, igual que el registro de envíos de alertas: lo
     * que se consulta casi siempre es «¿qué pasó ayer?», no el primer día del
     * sistema.
     */
    protected static function direccionPedida(Request $peticion): string
    {
        return self::opcion($peticion, 'direccion', ['asc', 'desc'], 'desc');
    }

    /**
     * @return list<array{clave: string, texto: string}>
     */
    public function aplicados(): array
    {
        $etiquetas = [];

        if ($this->desde !== '') {
            $etiquetas[] = ['clave' => 'desde', 'texto' => 'Desde: '.self::enFormatoColombiano($this->desde)];
        }

        if ($this->hasta !== '') {
            $etiquetas[] = ['clave' => 'hasta', 'texto' => 'Hasta: '.self::enFormatoColombiano($this->hasta)];
        }

        if ($this->usuario !== self::TODOS) {
            $etiquetas[] = ['clave' => 'usuario', 'texto' => 'Usuario: '.self::textoDe(self::usuarios(), $this->usuario)];
        }

        if ($this->modulo !== self::TODOS) {
            $etiquetas[] = ['clave' => 'modulo', 'texto' => 'Módulo: '.self::textoDe(self::modulos(), $this->modulo)];
        }

        if ($this->accion !== self::TODOS) {
            $etiquetas[] = ['clave' => 'accion', 'texto' => 'Acción: '.self::textoDe(self::acciones(), $this->accion)];
        }

        return $etiquetas;
    }

    /**
     * @return array<string, string>
     */
    protected function filtrosParaLaInterfaz(): array
    {
        return [
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'usuario' => $this->usuario,
            'modulo' => $this->modulo,
            'accion' => $this->accion,
        ];
    }

    /**
     * @return list<string>
     */
    private static function idsDeUsuarios(): array
    {
        return self::valoresDe(self::usuarios());
    }

    /**
     * @return list<string>
     */
    private static function valoresDeAccion(): array
    {
        return array_map(fn (AccionAuditada $accion): string => $accion->value, AccionAuditada::cases());
    }

    /**
     * «Registro afectado» (documento 1, 8.2): el nombre del empleado cuando la
     * línea es de alguno de los suyos, o el nombre corto de la tabla cuando no
     * hay ninguno que resolver.
     *
     * Se lee de los propios valores guardados, no del registro en vivo: si ya
     * se borró, la línea tiene que poder seguir diciendo de quién hablaba.
     */
    private static function etiquetaDelRegistro(Auditoria $linea): string
    {
        $datos = ($linea->valores_nuevos ?? []) + ($linea->valores_anteriores ?? []);

        return match ($linea->modelo) {
            Persona::class => self::nombreDePersona($datos) ?? self::generico($linea),
            User::class => is_string($datos['name'] ?? null) ? $datos['name'] : self::generico($linea),
            Tenant::class => is_string($datos['nombre'] ?? null) ? $datos['nombre'] : self::generico($linea),
            Vinculacion::class => self::nombreDeLaPersona($datos['persona_id'] ?? null) ?? self::generico($linea),
            default => is_numeric($datos['vinculacion_id'] ?? null)
                ? (self::nombreDeLaVinculacion($datos['vinculacion_id']) ?? self::generico($linea))
                : self::generico($linea),
        };
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function nombreDePersona(array $datos): ?string
    {
        $nombre = implode(' ', array_filter(array_map(
            fn (string $campo): string => trim((string) ($datos[$campo] ?? '')),
            ['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido'],
        ), fn (string $parte): bool => $parte !== ''));

        return $nombre !== '' ? $nombre : null;
    }

    private static function nombreDeLaPersona(mixed $id): ?string
    {
        if (! is_numeric($id)) {
            return null;
        }

        return Persona::query()->withTrashed()->find((int) $id)?->nombreCompleto();
    }

    private static function nombreDeLaVinculacion(mixed $id): ?string
    {
        if (! is_numeric($id)) {
            return null;
        }

        $vinculacion = Vinculacion::query()->withTrashed()->with('persona')->find((int) $id);

        return $vinculacion?->persona?->nombreCompleto();
    }

    private static function generico(Auditoria $linea): string
    {
        return class_basename($linea->modelo).' #'.$linea->registro_id;
    }

    /**
     * Los dos lados del cambio, campo por campo, para el panel lateral
     * (documento 3, 5.6): «los valores anteriores y nuevos lado a lado».
     *
     * @return list<array{campo: string, antes: string, despues: string}>
     */
    private static function cambios(Auditoria $linea): array
    {
        $anteriores = $linea->valores_anteriores ?? [];
        $nuevos = $linea->valores_nuevos ?? [];

        $campos = array_diff(
            array_unique([...array_keys($anteriores), ...array_keys($nuevos)]),
            self::CAMPOS_SIN_INTERES,
        );

        sort($campos);

        return array_map(fn (string $campo): array => [
            'campo' => str_replace('_', ' ', $campo),
            'antes' => self::valorLegible($anteriores[$campo] ?? null),
            'despues' => self::valorLegible($nuevos[$campo] ?? null),
        ], $campos);
    }

    private static function valorLegible(mixed $valor): string
    {
        if ($valor === null) {
            return Formato::VACIO;
        }

        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        if (is_array($valor)) {
            return implode(', ', array_map(fn (mixed $item): string => (string) $item, $valor));
        }

        // Una fecha guardada como valor de auditoría no siempre llega en
        // `AAAA-MM-DD`: el mismo campo puede quedar como fecha sola en la
        // creación y como marca de tiempo completa en la edición, según cómo
        // Eloquent serializó ese valor concreto para guardarlo dirty. Se
        // reconoce por el prefijo y se lleva a la zona horaria de la empresa
        // antes de formatear, para que una hora en UTC no cambie el día.
        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}:\d{2}(\.\d+)?Z?)?$/', $valor) === 1) {
            return Formato::fecha(Carbon::parse($valor)->setTimezone(config('app.timezone')));
        }

        return (string) $valor;
    }

    /**
     * El resumen de una línea (documento 3, 5.6): lo que se lee en la columna
     * de la tabla, sin abrir el panel lateral. En una edición son los campos
     * que cambiaron, uno por uno; en las demás acciones no hay nada que
     * comparar y el texto lo dice.
     *
     * @param  list<array{campo: string, antes: string, despues: string}>  $cambios
     */
    private static function detalle(AccionAuditada $accion, array $cambios): string
    {
        return match ($accion) {
            AccionAuditada::Creado => 'Registro creado.',
            AccionAuditada::Eliminado => 'Registro eliminado.',
            AccionAuditada::Restaurado => 'Registro restaurado.',
            AccionAuditada::Editado => $cambios === [] ? 'Sin cambios visibles.' : implode('; ', array_map(
                fn (array $cambio): string => "{$cambio['campo']}: {$cambio['antes']} → {$cambio['despues']}",
                $cambios,
            )),
        };
    }
}
