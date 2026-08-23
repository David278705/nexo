<?php

namespace App\Alertas;

use App\Dominio\Alertas\EstadoDeAlerta;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Listados\Listado;
use App\Models\AlertaEnviada;
use App\Notificaciones\CorreoDeAlerta;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * El registro de envíos del cliente (documento 2, sección 5.5) y sus tres
 * filtros: rango de fechas, tipo y estado.
 *
 * Una fila es un correo, no un empleado. Trae lo que la sección pide que se
 * pueda consultar —«a quién, cuándo, con qué asunto y con qué resultado»— y
 * además el cuerpo entero, porque esta pantalla es también el canal alterno: si
 * el correo se perdió, el aviso tiene que poder leerse aquí y no solo constar
 * que se mandó.
 *
 * **Lo que se ve depende de los módulos del usuario**, no del módulo Alertas:
 * `AlertasDelUsuario` decide los tipos y esta clase los recibe ya resueltos. La
 * lista se aplica siempre, incluso vacía, y una lista vacía no devuelve nada,
 * que es lo correcto: un usuario que no recibe ninguna alerta por correo tampoco
 * tiene ninguna que leer.
 *
 * El armazón —la dirección, el orden, la paginación— lo pone `Listado`.
 *
 * @extends Listado<AlertaEnviada>
 */
class ListadoDeAlertas extends Listado
{
    /**
     * @param  list<TipoDeAlerta>  $visibles
     */
    protected function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        public readonly string $tipo,
        public readonly string $estado,
        private readonly array $visibles,
        string $orden,
        string $direccion,
        int $porPagina,
    ) {
        parent::__construct($orden, $direccion, $porPagina);
    }

    /**
     * @param  list<TipoDeAlerta>  $visibles
     */
    public static function desde(Request $peticion, array $visibles): self
    {
        return new self(
            desde: self::fecha($peticion, 'desde'),
            hasta: self::fecha($peticion, 'hasta'),
            // La lista blanca del filtro son los tipos que este usuario puede
            // ver, no los trece: pedir uno ajeno por la dirección se cae a
            // «todos» y sigue sin sacar nada, porque el filtro de visibles se
            // aplica igual.
            tipo: self::filtro($peticion, 'tipo', array_map(
                fn (TipoDeAlerta $tipo): string => $tipo->value,
                $visibles,
            )),
            estado: self::filtro($peticion, 'estado', array_map(
                fn (EstadoDeAlerta $estado): string => $estado->value,
                EstadoDeAlerta::cases(),
            )),
            visibles: $visibles,
            orden: self::ordenPedido($peticion),
            direccion: self::direccionPedida($peticion),
            porPagina: self::porPagina($peticion),
        );
    }

    /**
     * Las opciones del filtro de estado, con su etiqueta.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public static function estados(): array
    {
        return array_map(
            fn (EstadoDeAlerta $estado): array => [
                'valor' => $estado->value,
                'texto' => $estado->etiqueta(),
            ],
            EstadoDeAlerta::cases(),
        );
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

        if ($this->tipo !== self::TODOS) {
            $etiquetas[] = ['clave' => 'tipo', 'texto' => 'Tipo: '.TipoDeAlerta::from($this->tipo)->etiqueta()];
        }

        if ($this->estado !== self::TODOS) {
            $etiquetas[] = ['clave' => 'estado', 'texto' => 'Estado: '.EstadoDeAlerta::from($this->estado)->etiqueta()];
        }

        return $etiquetas;
    }

    /**
     * Una fila de la tabla, con su detalle dentro.
     *
     * El asunto, el cuerpo y los destinatarios viajan con la fila y no se piden
     * aparte al abrir el detalle: son los que se guardaron el día del envío y no
     * cambian nunca más, así que una segunda vuelta al servidor traería
     * exactamente lo mismo. Y son **los que se enviaron**, no los que se
     * calcularían hoy: si el cargo de la persona cambió después, el registro
     * sigue contando lo que llegó al buzón.
     *
     * @return array<string, mixed>
     */
    public static function fila(AlertaEnviada $alerta): array
    {
        $persona = $alerta->vinculacion?->persona;

        return [
            'id' => (int) $alerta->getKey(),
            'programada' => Formato::fecha($alerta->fecha_programada),
            'enviada' => Formato::fechaYHora($alerta->enviada_en),
            'tipo' => $alerta->tipo->etiqueta(),
            // Un resumen mensual no es de nadie en particular (documento 1, 9.7)
            // y decirlo así evita que su raya se lea como un dato que falta.
            'empleado' => $persona?->nombreCompleto() ?? 'Toda la empresa',
            'estado' => $alerta->estado->etiqueta(),
            'tono' => $alerta->estado->tono(),
            'intentos' => $alerta->intentos,
            'destinatarios' => $alerta->destinatarios,
            'asunto' => $alerta->asunto,
            'parrafos' => CorreoDeAlerta::parrafosDe($alerta->cuerpo),
            'motivo' => (string) ($alerta->motivo_del_fallo ?? ''),
        ];
    }

    /**
     * @return Builder<AlertaEnviada>
     */
    protected function base(): Builder
    {
        return AlertaEnviada::query()->with(['vinculacion.persona']);
    }

    /**
     * @param  Builder<AlertaEnviada>  $consulta
     */
    protected function filtrar(Builder $consulta): void
    {
        $consulta->whereIn('tipo', array_map(
            fn (TipoDeAlerta $tipo): string => $tipo->value,
            $this->visibles,
        ));

        if ($this->desde !== '') {
            $consulta->whereDate('fecha_programada', '>=', $this->desde);
        }

        if ($this->hasta !== '') {
            $consulta->whereDate('fecha_programada', '<=', $this->hasta);
        }

        if ($this->tipo !== self::TODOS) {
            $consulta->where('tipo', $this->tipo);
        }

        if ($this->estado !== self::TODOS) {
            $consulta->where('estado', $this->estado);
        }
    }

    /**
     * @return array<string, callable(Builder<AlertaEnviada>, string): mixed>
     */
    protected static function columnasDeOrden(): array
    {
        return [
            'programada' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('fecha_programada', $sentido),
            'enviada' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('enviada_en', $sentido),
            'tipo' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('tipo', $sentido),
        ];
    }

    protected static function ordenPorDefecto(): string
    {
        return 'programada';
    }

    /**
     * La pantalla abre por lo más reciente, al revés que los demás listados.
     *
     * Un registro de envíos se consulta para responder «¿esto se mandó?», y lo
     * que se pregunta es siempre por lo de ayer o lo de la semana pasada. Abrirlo
     * por el correo más viejo obligaría a paginar hasta el final cada vez.
     */
    protected static function direccionPedida(Request $peticion): string
    {
        return self::opcion($peticion, 'direccion', ['asc', 'desc'], 'desc');
    }

    /**
     * @return array<string, string|int>
     */
    protected function filtrosParaLaInterfaz(): array
    {
        return [
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'tipo' => $this->tipo,
            'estado' => $this->estado,
        ];
    }
}
