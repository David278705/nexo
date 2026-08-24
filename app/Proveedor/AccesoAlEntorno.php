<?php

namespace App\Proveedor;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitaAlEntorno;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

/**
 * El acceso del proveedor al entorno de un cliente (documento 4, 4.4).
 *
 * El capítulo lo marca **CRÍTICO** y explica por qué existe: «va a ser necesario
 * entrar como el cliente para resolver un problema que él no sabe describir».
 * Y le pone tres condiciones que no son negociables. Las dos de software se
 * cumplen aquí y en dos middleware, no en la pantalla:
 *
 * 1. **Queda registrado en la auditoría de esa empresa.** Lo consigue guardar
 *    una fila de `visitas_al_entorno`, que lleva la empresa visitada: el
 *    observador escribe la línea en la auditoría de ella, con el `owner` como
 *    autor. Nadie escribe esa línea a mano (regla 4).
 * 2. **Es de solo lectura por defecto.** La visita nace sin escritura y
 *    `App\Http\Middleware\EntornoDeSoloLectura` corta en el servidor cualquier
 *    petición que no sea de lectura. Activarla es expreso, con su propio motivo,
 *    y deja **un registro aparte**: una segunda línea de auditoría sobre la
 *    misma fila.
 *
 * La tercera —que el contrato y el acuerdo de tratamiento de datos lo
 * mencionen— es de papel: decisiones A.2 y A.6 del documento 6.
 *
 * ## Cómo se sostiene el contexto
 *
 * La visita en curso vive en la sesión, **nunca en la dirección ni en un
 * formulario**: eso sería justo lo que prohíbe la regla de multiempresa. La
 * sesión solo guarda el identificador de la fila; de qué empresa es lo dice la
 * fila, que la escribió el servidor. `ResolverTenant` la lee al empezar cada
 * petición y fija el contexto de la empresa visitada.
 *
 * Se registra como singleton, así que la fila se busca una sola vez por
 * petición aunque la pregunten el middleware, la política de módulos y las
 * props compartidas.
 */
class AccesoAlEntorno
{
    /**
     * La clave de sesión donde vive el identificador de la visita en curso.
     */
    public const CLAVE_DE_SESION = 'visita_al_entorno';

    /**
     * Las tres condiciones del documento, con las palabras del prototipo. Se
     * leen en el modal antes de entrar, que es cuando hay que leerlas.
     *
     * @var list<string>
     */
    public const CONDICIONES = [
        'Queda registrado en la auditoría de esa empresa, visible para el cliente, indicando que '
            .'fue el proveedor.',
        'Es de solo lectura. Modificar datos exige activarlo de forma expresa, y esa activación '
            .'queda como un registro aparte.',
        'El acuerdo de tratamiento de datos lo contempla. Un acceso silencioso a datos de '
            .'empleados de un tercero es exactamente lo que ese acuerdo prohíbe.',
    ];

    public const TITULO_DE_LAS_CONDICIONES = 'Tres condiciones que no son negociables';

    public const AYUDA_DEL_MOTIVO = 'Queda en el registro. Escriba algo que tenga sentido dentro '
        .'de seis meses.';

    public const ETIQUETA_DE_ESCRITURA = 'Necesito modificar datos, no solo consultar';

    /**
     * Lo que el servidor responde a una escritura durante una visita de
     * consulta. Es el texto del 403, así que se escribe una sola vez y lo usan
     * el middleware y su prueba.
     */
    public const SOLO_LECTURA = 'Estás en el entorno del cliente en modo consulta, así que esta '
        .'acción no se ejecutó. Modificar datos de un cliente exige activarlo de forma expresa al '
        .'entrar, y queda registrado aparte en su auditoría.';

    /**
     * Lo que se le dice al `owner` que pide el panel sin haber salido antes.
     */
    public const PANEL_BLOQUEADO = 'Sigues dentro del entorno de un cliente. Sal del entorno para '
        .'volver al panel: así la visita queda cerrada en el registro y no abierta para siempre.';

    private ?VisitaAlEntorno $visita = null;

    private bool $resuelta = false;

    /**
     * Olvida lo que se resolvió, para volver a preguntarlo.
     *
     * Lo llama `ResolverTenant` al empezar cada petición, por lo mismo que ahí
     * se empieza limpiando el contexto de empresa: esta clase es un singleton y
     * un contenedor que viva más que una petición —Octane, la cola, una prueba
     * que encadena varias peticiones— arrastraría la visita de la anterior. Un
     * dato memorizado que sobrevive a su petición es exactamente la clase de
     * fuga que el aislamiento existe para impedir.
     */
    public function olvidar(): void
    {
        $this->visita = null;
        $this->resuelta = false;
    }

    /**
     * La visita en curso, o null si el usuario no está dentro de ninguna.
     *
     * Una fila ya cerrada no cuenta y además se limpia de la sesión: el
     * identificador de una visita terminada no vuelve a servir de llave.
     */
    public function enCurso(): ?VisitaAlEntorno
    {
        if ($this->resuelta) {
            return $this->visita;
        }

        $this->resuelta = true;

        $identificador = Session::get(self::CLAVE_DE_SESION);

        if (! is_int($identificador) && ! is_string($identificador)) {
            return $this->visita = null;
        }

        $visita = VisitaAlEntorno::query()->find($identificador);

        if (! $visita instanceof VisitaAlEntorno || ! $visita->estaEnCurso()) {
            Session::forget(self::CLAVE_DE_SESION);

            return $this->visita = null;
        }

        return $this->visita = $visita;
    }

    /**
     * La empresa dentro de la que se está, o null fuera de una visita.
     */
    public function empresaVisitada(): ?Tenant
    {
        $visita = $this->enCurso();

        if (! $visita instanceof VisitaAlEntorno) {
            return null;
        }

        $empresa = $visita->tenant()->first();

        return $empresa instanceof Tenant ? $empresa : null;
    }

    /**
     * Si además de mirar se puede escribir. Falso siempre que no haya visita:
     * fuera de una visita esta clase no decide nada.
     */
    public function permiteEscribir(): bool
    {
        return $this->enCurso()?->permiteEscribir() === true;
    }

    /**
     * Abre la visita: guarda la constancia y deja al `owner` dentro.
     *
     * La escritura **no se concede al crear la fila**, ni siquiera cuando se
     * pide desde el mismo formulario. Se crea la visita en modo consulta y, si
     * hacía falta escribir, se activa en una segunda escritura: son dos hechos
     * distintos y el documento pide que el segundo deje «un registro aparte».
     * Dos líneas de auditoría, las dos en la auditoría del cliente.
     */
    public function iniciar(Tenant $empresa, User $owner, string $motivo, bool $conEscritura = false): VisitaAlEntorno
    {
        $visita = VisitaAlEntorno::query()->create([
            'tenant_id' => $empresa->getKey(),
            'user_id' => $owner->getKey(),
            'motivo' => $motivo,
        ]);

        if ($conEscritura) {
            $visita->update([
                'escritura_activada_en' => Carbon::now(),
                'motivo_de_escritura' => $motivo,
            ]);
        }

        Session::put(self::CLAVE_DE_SESION, $visita->getKey());

        $this->visita = $visita;
        $this->resuelta = true;

        return $visita;
    }

    /**
     * Cierra la visita en curso, si la hay.
     *
     * Cerrarla es otra escritura sobre la fila, así que en la auditoría del
     * cliente queda también la salida: sin ella, la constancia diría que el
     * proveedor entró y nunca se fue.
     */
    public function terminar(): ?VisitaAlEntorno
    {
        $visita = $this->enCurso();

        Session::forget(self::CLAVE_DE_SESION);

        $this->visita = null;
        $this->resuelta = true;

        if (! $visita instanceof VisitaAlEntorno) {
            return null;
        }

        $visita->update(['terminada_en' => Carbon::now()]);

        return $visita;
    }

    /**
     * La franja permanente que se ve arriba mientras se está dentro, o null
     * fuera de una visita.
     *
     * Se comparte con **todas** las pantallas del entorno del cliente: el
     * proveedor tiene que saber en todo momento que está mirando datos ajenos,
     * y con qué permiso. Que el cliente no la vea es correcto: él ya tiene su
     * constancia en la auditoría, que es la que el documento le promete.
     *
     * @return array<string, mixed>|null
     */
    public function paraLaFranja(): ?array
    {
        $visita = $this->enCurso();

        if (! $visita instanceof VisitaAlEntorno) {
            return null;
        }

        $empresa = $this->empresaVisitada();
        $escribe = $visita->permiteEscribir();

        return [
            'empresa' => $empresa?->getAttribute('nombre') ?? '',
            'modo' => $escribe ? 'Modo edición' : 'Modo consulta',
            'tono' => $escribe ? 'advertencia' : 'informacion',
            'explicacion' => $escribe
                ? 'Estás dentro como proveedor con la escritura activada. Todo lo que cambies queda '
                    .'en la auditoría de esta empresa, a nombre tuyo.'
                : 'Estás dentro como proveedor, de solo lectura. Tu entrada quedó registrada en la '
                    .'auditoría de esta empresa y el cliente la puede ver.',
            'motivo' => $visita->motivo,
            'salir' => 'Salir del entorno',
        ];
    }

    /**
     * El historial de entradas a una empresa, para su ficha en el panel.
     *
     * Sale de la tabla y no de la auditoría porque aquí se lee del lado del
     * proveedor: la auditoría es lo que ve el cliente y esto es lo que ve quien
     * atiende al cliente. Son la misma verdad mirada desde los dos lados.
     *
     * @return list<array<string, mixed>>
     */
    public static function historial(Tenant $empresa): array
    {
        return VisitaAlEntorno::query()
            ->with('usuario')
            ->where('tenant_id', $empresa->getKey())
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (VisitaAlEntorno $visita): array => [
                'id' => $visita->getKey(),
                'cuando' => Formato::fechaYHora($visita->created_at),
                'quien' => (string) ($visita->usuario()->first()?->getAttribute('name') ?? 'El proveedor'),
                'motivo' => $visita->motivo,
                'modo' => $visita->escritura_activada_en !== null ? 'Con edición' : 'Consulta',
                'tono' => $visita->escritura_activada_en !== null ? 'advertencia' : 'neutro',
                'duracion' => self::duracion($visita),
            ])
            ->values()
            ->all();
    }

    /**
     * Cuánto duró la visita, dicho en palabras.
     *
     * Una visita abierta lo dice en vez de enseñar un número: es información
     * distinta —alguien sigue dentro ahora mismo— y no una duración de cero.
     */
    private static function duracion(VisitaAlEntorno $visita): string
    {
        $minutos = $visita->minutos();

        if ($minutos === null) {
            return 'Abierta ahora';
        }

        return $minutos < 1 ? 'Menos de un minuto' : Formato::minutos($minutos);
    }
}
