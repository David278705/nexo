<?php

namespace App\Console\Commands;

use App\Autenticacion\Invitaciones;
use App\Autorizacion\Rol;
use App\Models\User;
use App\Notificaciones\InvitacionAlSistema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Invita a una persona al equipo del proveedor: crea su cuenta `owner` y emite
 * el enlace con el que define su propia contraseña.
 *
 * **Es la única puerta al panel en un servidor recién desplegado.**
 * `DesarrolloSeeder` crea un owner con contraseña conocida, pero solo en
 * local —en producción sería una puerta trasera—, y la sección «Equipo» del
 * panel todavía no está construida. Sin este comando, después del primer
 * despliegue nadie podría entrar.
 *
 * **La contraseña nunca pasa por la terminal.** La define la persona desde el
 * enlace, con las mismas reglas de cualquier invitación, así que no queda ni en
 * el historial del shell ni en los registros de Railway.
 *
 * **El enlace se imprime siempre**, además de intentar mandarlo por correo: lo
 * normal es que el primer owner se cree antes de que el correo esté
 * configurado. Si el envío falla, el comando lo dice y el enlace sigue sirviendo.
 *
 * Volver a correrlo con el correo de un owner existente emite una invitación
 * nueva y vence la anterior: es lo que se hace con quien perdió el enlace.
 */
class InvitarAlEquipo extends Command
{
    protected $signature = 'equipo:invitar
        {correo : Correo de la persona}
        {nombre : Nombre con el que aparecerá en el panel}';

    protected $description = 'Crea una cuenta del equipo del proveedor (rol owner) y emite su enlace de invitación';

    /**
     * Los transportes de Laravel que no mandan nada de verdad. Con ellos el
     * envío «funciona», y decir «invitación enviada» sería mentir.
     */
    private const TRANSPORTES_QUE_NO_ENVIAN = ['log', 'array'];

    public function handle(Invitaciones $invitaciones): int
    {
        $correo = mb_strtolower(trim((string) $this->argument('correo')));
        $nombre = trim((string) $this->argument('nombre'));

        $validacion = Validator::make(
            ['correo' => $correo, 'nombre' => $nombre],
            ['correo' => ['required', 'email'], 'nombre' => ['required', 'string', 'max:255']],
        );

        if ($validacion->fails()) {
            foreach ($validacion->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existente = User::withTrashed()->where('email', $correo)->first();

        if ($existente instanceof User && ! $existente->esOwner()) {
            $this->error("{$correo} ya es usuario de una empresa cliente: una cuenta no puede ser de una empresa y del proveedor a la vez.");

            return self::FAILURE;
        }

        if ($existente instanceof User && $existente->trashed()) {
            $this->error("La cuenta de {$correo} está desactivada. Reactivarla es una decisión aparte, no un efecto de invitarla.");

            return self::FAILURE;
        }

        $usuario = $existente ?? User::query()->create([
            'tenant_id' => null,
            'rol' => Rol::Owner,
            'name' => $nombre,
            'email' => $correo,
            // Nadie la ve ni la usa: el acceso se abre con el enlace, igual que
            // en `UsuariosDeLaEmpresa` y en `CreacionDeEmpresa`.
            'password' => Str::password(64),
        ]);

        $token = $invitaciones->generar($usuario);

        $this->enviar($usuario, $token);

        $this->newLine();
        $this->line('Enlace de invitación (sirve una sola vez y vence en '.Invitaciones::DIAS_DE_VIGENCIA.' días):');
        $this->line(route('invitacion.formulario', $token));
        $this->newLine();
        $this->warn('Trátalo como una contraseña: no lo pegues en chats ni lo dejes guardado en documentos.');

        return self::SUCCESS;
    }

    private function enviar(User $usuario, string $token): void
    {
        $transporte = (string) config('mail.default');

        if (in_array($transporte, self::TRANSPORTES_QUE_NO_ENVIAN, true)) {
            $this->warn("El correo no sale de esta máquina (MAIL_MAILER={$transporte}): usa el enlace de abajo.");

            return;
        }

        try {
            $usuario->notify(new InvitacionAlSistema($token));
            $this->info("Invitación enviada a {$usuario->email}.");
        } catch (Throwable $error) {
            report($error);
            $this->warn('No se pudo mandar el correo: '.$error->getMessage());
            $this->warn('La invitación sí quedó creada: usa el enlace de abajo.');
        }
    }
}
