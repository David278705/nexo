<?php

namespace App\Configuracion;

use App\Autenticacion\Invitaciones;
use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Novedades\ConfirmacionDeBorrado;
use App\Presentacion\Formato;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * La pestaña «Usuarios y permisos» de Configuración (documento 3, 5.7): «Listado
 * de usuarios con su rol y módulos. Al crear uno, se pide nombre y correo, se
 * marcan los módulos y se envía la invitación. Un usuario se desactiva, no se
 * borra.»
 *
 * Es la mitad que le faltaba a F1-12: el superadministrador podía cambiar los
 * tres parámetros de cálculo pero no gestionar su propio equipo, y ese
 * capítulo tocaba entrar aquí, no en el panel del proveedor —que gestiona la
 * relación *con* el cliente, no la de sus usuarios *entre sí*—.
 *
 * Tres decisiones de esta tarea:
 *
 * 1. **El superadministrador no se toca desde aquí.** Tiene todos los módulos
 *    por su rol y no por asignación (documento 1, capítulo 3), y el documento
 *    no describe una forma de crear un segundo o de desactivar al único que
 *    existe. La pantalla lo enseña en el listado, sin acciones.
 * 2. **La invitación reutiliza `Invitaciones`,** el mismo mecanismo que ya usa
 *    la creación de empresa (F1B-02) del lado del proveedor. Nadie escribe una
 *    contraseña aquí tampoco: se genera una al azar que nadie llega a ver.
 * 3. **«Desactivar» es `SoftDeletes::delete()`**, sin más. Es justamente el
 *    borrado lógico que la regla 4 del proyecto exige, y como `User` no estaba
 *    en la lista de exclusión de `ObservadorDeAuditoria`, la línea de auditoría
 *    la escribe el observador solo, como pide el capítulo 6.3: «esta acción
 *    queda registrada en la auditoría».
 */
class UsuariosDeLaEmpresa
{
    private function __construct(private readonly Tenant $empresa) {}

    public static function de(Tenant $empresa): self
    {
        return new self($empresa);
    }

    /**
     * Todas las props de la pestaña.
     *
     * @return array<string, mixed>
     */
    public function paraLaPantalla(): array
    {
        return [
            'usuarios' => $this->listado(),
            'modulosAsignables' => self::moduloOpciones(),
        ];
    }

    /**
     * Los usuarios de la empresa, del rol más alto al más bajo y luego por
     * nombre. Un usuario desactivado no aparece: dejó de ser parte del equipo
     * que se administra desde aquí, aunque sus líneas de auditoría se
     * conserven.
     *
     * @return list<array<string, mixed>>
     */
    public function listado(): array
    {
        return $this->empresa->usuarios()
            ->with('modulos')
            ->orderBy('rol')
            ->orderBy('name')
            ->get()
            ->map(fn (User $usuario) => [
                'id' => $usuario->getKey(),
                'nombre' => (string) $usuario->getAttribute('name'),
                'correo' => (string) $usuario->getAttribute('email'),
                'rol' => $usuario->rol->etiqueta(),
                'esSuperadministrador' => $usuario->esSuperadministrador(),
                'modulos' => ParametrosDeLaEmpresa::modulosDe($usuario),
                'modulosAsignados' => $usuario->modulosAsignados()
                    ->map(fn (Modulo $modulo) => $modulo->value)
                    ->values()
                    ->all(),
                'invitacionPendiente' => $this->tieneInvitacionSinAceptar($usuario),
                'consecuenciaDeDesactivar' => self::consecuenciaDeDesactivar($usuario),
            ])
            ->values()
            ->all();
    }

    /**
     * Los módulos que se pueden marcar, para el formulario de crear y el de
     * editar: los mismos, en el mismo orden, en los dos.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public static function moduloOpciones(): array
    {
        return array_map(
            fn (Modulo $modulo) => ['valor' => $modulo->value, 'texto' => $modulo->etiqueta()],
            Modulo::asignables(),
        );
    }

    /**
     * Crea el administrador, le asigna los módulos marcados y le manda la
     * invitación. Las tres escrituras van en una sola transacción: un
     * administrador sin invitación no tiene forma de entrar nunca, así que no
     * puede quedar creado si el correo falla a mitad de camino.
     *
     * @param  array{nombre: string, correo: string, modulos?: list<string>}  $datos
     */
    public function crear(array $datos): User
    {
        return DB::transaction(function () use ($datos): User {
            $usuario = User::query()->create([
                'tenant_id' => $this->empresa->getKey(),
                'rol' => Rol::Administrador,
                'name' => $datos['nombre'],
                'email' => $datos['correo'],

                // Nadie la ve ni la usa: el acceso se abre con el enlace de la
                // invitación, igual que en `CreacionDeEmpresa`.
                'password' => Str::password(64),
            ]);

            foreach ($datos['modulos'] ?? [] as $modulo) {
                $usuario->modulos()->create(['modulo' => $modulo]);
            }

            app(Invitaciones::class)->emitir($usuario);

            return $usuario;
        });
    }

    /**
     * Reemplaza los módulos asignados por los marcados en el formulario.
     *
     * Borra los que ya no están y crea los que faltan en vez de comparar
     * conjunto a conjunto: son como mucho once filas, y la diferencia no vale
     * la complejidad de calcularla. Cada alta y cada baja queda en la
     * auditoría por su cuenta, como cualquier otra fila de `user_modulos`.
     *
     * @param  list<string>  $modulos
     */
    public function actualizarModulos(User $usuario, array $modulos): void
    {
        DB::transaction(function () use ($usuario, $modulos): void {
            $usuario->modulos()->delete();

            foreach ($modulos as $modulo) {
                $usuario->modulos()->create(['modulo' => $modulo]);
            }
        });
    }

    /**
     * Desactiva al usuario: borrado lógico, sin más. `ObservadorDeAuditoria`
     * escribe sola la línea de «eliminado» porque `User` no está en su lista
     * de exclusión, y `SesionController` deja de encontrarlo al iniciar
     * sesión porque el global scope de `SoftDeletes` lo excluye de la
     * consulta por correo: no hace falta ningún candado aparte para que deje
     * de poder entrar.
     */
    public function desactivar(User $usuario): void
    {
        $usuario->delete();
    }

    /**
     * @return array<string, list<string>>
     */
    public static function reglasParaCrear(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            // El correo es único en todo el sistema, no por empresa: es con
            // lo que se inicia sesión, y el inicio de sesión ocurre antes de
            // saber de qué empresa es quien escribe (mismo criterio de
            // `CreacionDeEmpresa`).
            'correo' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'modulos' => ['array'],
            'modulos.*' => ['string', Rule::in(Modulo::valoresAsignables())],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function reglasParaModulos(): array
    {
        return [
            'modulos' => ['array'],
            'modulos.*' => ['string', Rule::in(Modulo::valoresAsignables())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function atributos(): array
    {
        return [
            'nombre' => 'nombre',
            'correo' => 'correo',
            'modulos' => 'módulos',
        ];
    }

    /**
     * La confirmación tras invitar, con la misma cifra de vigencia que ya usa
     * la invitación del superadministrador en `CreacionDeEmpresa`.
     */
    public static function confirmacionDeInvitacion(User $usuario): string
    {
        return 'Se invitó a '.$usuario->getAttribute('name').'. La invitación llegó a '
            .$usuario->getAttribute('email').' y vence en '
            .Formato::dias(Invitaciones::DIAS_DE_VIGENCIA).'.';
    }

    /**
     * La confirmación tras guardar los módulos.
     */
    public static function confirmacionDeModulos(User $usuario): string
    {
        return 'Se actualizaron los módulos de '.$usuario->getAttribute('name').'.';
    }

    /**
     * La plantilla del documento 3, 6.3, para la única acción destructiva de
     * esta pantalla. No hay ningún número que calcular —desactivar no mueve
     * ningún saldo—, así que la consecuencia es la del acceso, no la de un
     * cómputo.
     */
    public static function consecuenciaDeDesactivar(User $usuario): string
    {
        if ($usuario->esSuperadministrador()) {
            return '';
        }

        return 'Vas a desactivar a '.$usuario->getAttribute('name').'. No podrá volver a iniciar '
            .'sesión, pero su historial en la auditoría se conserva. '.ConfirmacionDeBorrado::AUDITORIA;
    }

    /**
     * La confirmación tras desactivar.
     */
    public static function confirmacionDeDesactivar(User $usuario): string
    {
        return $usuario->getAttribute('name').' quedó desactivado. No podrá iniciar sesión.';
    }

    /**
     * Si el usuario sigue sin estrenar su cuenta. Mismo criterio que
     * `FichaDeEmpresa::tieneInvitacionSinAceptar()` del lado del proveedor: se
     * pregunta por la invitación y no por la contraseña, porque la contraseña
     * siempre existe —se genera al azar al crear la cuenta—.
     */
    private function tieneInvitacionSinAceptar(User $usuario): bool
    {
        return $usuario->invitaciones()
            ->whereNull('aceptada_en')
            ->exists();
    }
}
