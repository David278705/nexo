<?php

namespace App\Proveedor;

use App\Models\AccesoDePrueba;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Los accesos de prueba con vigencia limitada (documento 4, capítulo 10 y
 * 12.1): «se le entrega un acceso de prueba con vigencia limitada para que lo
 * explore solo. David genera el acceso».
 *
 * **Cuántos días dura no es una constante de esta clase.** El documento 6,
 * D.12, deja sin decidir la vigencia del usuario de demostración, y el
 * documento 4 dice quién la decide y cuándo: David, cada vez que genera un
 * acceso. Por eso `generar()` recibe los días como argumento —lo que escribe
 * quien lo genera en el formulario— y no hay ningún valor por omisión
 * inventado aquí ni en el archivo de configuración.
 *
 * El token se guarda con hash y se devuelve en claro una sola vez, igual que
 * `App\Autenticacion\Invitaciones`: quien lea la tabla no puede fabricar el
 * enlace de nadie.
 */
class AccesosDePrueba
{
    public function __construct(private readonly AmbienteDeDemostracion $ambiente) {}

    /**
     * Genera un acceso nuevo con su propia cuenta de prueba, poblada con la
     * planta de la demostración (`PROD-17`), y devuelve el token en claro, el
     * único momento en que existe fuera de su hash.
     */
    public function generar(string $nombreProspecto, int $diasDeVigencia): string
    {
        $tenant = $this->ambiente->crearCuentaDePrueba($nombreProspecto);
        $token = Str::random(64);
        $ahora = Carbon::now();

        AccesoDePrueba::query()->create([
            'tenant_id' => $tenant->getKey(),
            'nombre_prospecto' => $nombreProspecto,
            'token' => $this->hash($token),
            'dias_de_vigencia' => $diasDeVigencia,
            'generado_en' => $ahora,
            'expira_en' => $ahora->clone()->addDays($diasDeVigencia),
        ]);

        return $token;
    }

    /**
     * Los accesos que todavía sirven para entrar, del más reciente al más
     * antiguo: es lo que el panel llama «los que están activos» (documento 4,
     * capítulo 10).
     *
     * @return Collection<int, AccesoDePrueba>
     */
    public function activos(): Collection
    {
        return AccesoDePrueba::query()
            ->whereNull('revocado_en')
            ->where('expira_en', '>', Carbon::now())
            ->orderByDesc('generado_en')
            ->get();
    }

    /**
     * El acceso de ese token, solo si todavía sirve para entrar.
     */
    public function vigente(string $token): ?AccesoDePrueba
    {
        $acceso = AccesoDePrueba::query()
            ->where('token', $this->hash($token))
            ->first();

        if (! $acceso instanceof AccesoDePrueba || ! $acceso->estaVigente()) {
            return null;
        }

        return $acceso;
    }

    /**
     * Dice que se acaba de usar. No inhabilita el token: un acceso de prueba
     * dura varios días y quien lo recibió puede volver a entrar cuantas veces
     * quiera mientras no venza ni se revoque, a diferencia de una invitación
     * de un solo uso.
     */
    public function registrarUso(AccesoDePrueba $acceso): void
    {
        $acceso->forceFill(['ultimo_uso_en' => Carbon::now()])->save();
    }

    /**
     * Corta el acceso antes de su vencimiento, para cuando el prospecto ya
     * decidió o cuando David quiere cerrar el enlace por cualquier otro
     * motivo.
     */
    public function revocar(AccesoDePrueba $acceso): void
    {
        $acceso->forceFill(['revocado_en' => Carbon::now()])->save();
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
