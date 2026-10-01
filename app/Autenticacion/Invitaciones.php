<?php

namespace App\Autenticacion;

use App\Models\Invitacion;
use App\Models\User;
use App\Notificaciones\InvitacionAlSistema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Crear, buscar y aceptar invitaciones de primer ingreso.
 *
 * El token se genera aquí y se devuelve en claro **una sola vez**, para meterlo
 * en el correo. En la tabla queda su hash, así que el enlace de nadie se puede
 * reconstruir leyendo la base de datos.
 */
class Invitaciones
{
    /**
     * Cuánto vale un enlace de invitación, en días.
     *
     * El documento fija la caducidad del enlace de recuperación —una hora—,
     * pero no la de la invitación, que es otra cosa: a la recuperación se
     * responde en el momento, y a una invitación puede que alguien entre el
     * lunes siguiente. Siete días es el plazo elegido; queda anotado en
     * `ESTADO.md` para confirmarlo.
     */
    public const DIAS_DE_VIGENCIA = 7;

    /**
     * Emite una invitación para el usuario y le manda el correo.
     *
     * Devuelve el token **en claro**, que es lo único que no queda guardado en
     * ninguna parte: en la tabla vive su hash. Quien lo necesite para algo que
     * no sea el correo tiene que pedirlo aquí.
     *
     * Las invitaciones anteriores del mismo usuario se dan por caducadas: si se
     * reenvía el enlace, el viejo tiene que dejar de servir.
     */
    public function emitir(User $usuario): string
    {
        $token = $this->generar($usuario);

        $usuario->notify(new InvitacionAlSistema($token));

        return $token;
    }

    /**
     * La invitación sin el correo: deja vencidas las anteriores, guarda la
     * nueva y devuelve el token en claro, que es la única vez que existe.
     *
     * Vive aparte de `emitir()` para `equipo:invitar`, que crea al primer
     * `owner` de un servidor recién desplegado, cuando lo normal es que el
     * correo todavía no esté configurado. Ese comando necesita el enlace aunque
     * el envío falle, y con `emitir()` el token se perdería con la excepción.
     */
    public function generar(User $usuario): string
    {
        Invitacion::query()
            ->where('user_id', $usuario->getKey())
            ->whereNull('aceptada_en')
            ->update(['expira_en' => Carbon::now()]);

        $token = Str::random(64);

        Invitacion::create([
            'user_id' => $usuario->getKey(),
            'token' => $this->hash($token),
            'expira_en' => Carbon::now()->addDays(self::DIAS_DE_VIGENCIA),
        ]);

        return $token;
    }

    /**
     * La invitación de ese token, solo si todavía sirve.
     */
    public function vigente(string $token): ?Invitacion
    {
        $invitacion = Invitacion::query()
            ->with('usuario')
            ->where('token', $this->hash($token))
            ->first();

        if (! $invitacion instanceof Invitacion || ! $invitacion->estaVigente()) {
            return null;
        }

        return $invitacion;
    }

    /**
     * Define la contraseña del invitado y cierra la invitación.
     *
     * La cuenta queda además con el correo verificado: acabar de abrir un
     * enlace que solo llegó a ese buzón es exactamente la prueba que la
     * verificación de correo busca.
     */
    public function aceptar(Invitacion $invitacion, string $contrasena): User
    {
        /** @var User $usuario */
        $usuario = $invitacion->usuario()->firstOrFail();

        $usuario->forceFill([
            'password' => $contrasena,
            'email_verified_at' => $usuario->email_verified_at ?? Carbon::now(),
        ])->save();

        $invitacion->forceFill(['aceptada_en' => Carbon::now()])->save();

        return $usuario;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
