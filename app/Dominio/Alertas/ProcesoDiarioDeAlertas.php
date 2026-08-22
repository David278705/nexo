<?php

namespace App\Dominio\Alertas;

use App\Dominio\Calculo\MotorDeCalculo;
use App\Jobs\EnviarAlerta;
use App\Models\AlertaEnviada;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use App\Proveedor\EstadoDeEmpresa;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

/**
 * El proceso programado que manda las alertas (documento 2, 5.1).
 *
 * Hace lo mismo todos los días y en este orden:
 *
 * 1. Recorre las empresas activas **una por una**, fijando el contexto de cada
 *    una. Nunca una consulta global: es la regla explícita de aislamiento para
 *    los comandos programados, y `ContextoTenant::paraTenant()` garantiza que
 *    el contexto se restaure aunque la vuelta falle.
 * 2. Para cada empresa, evalúa cada regla registrada contra cada fecha que le
 *    toca al día de hoy.
 * 3. Registra cada alerta que corresponde. Si el índice único la rechaza, es
 *    que ya se había mandado y no pasa nada más.
 * 4. Encola el correo de las que sí quedaron registradas.
 *
 * El envío no ocurre aquí. Aquí se registra y se encola; enviar es del
 * trabajador de la cola, que es quien puede reintentar sin bloquear el
 * recorrido de las demás empresas.
 *
 * ## Una empresa que falla no detiene a las otras
 *
 * Cada vuelta va dentro de su propio bloque: si la evaluación de una empresa
 * revienta, se anota y el proceso sigue con la siguiente. Un dato roto en un
 * cliente no puede dejar sin alertas a los demás, que es justo lo que se vende.
 */
class ProcesoDiarioDeAlertas
{
    public function __construct(
        private readonly ContextoTenant $contexto,
        private readonly CatalogoDeReglas $catalogo,
        private readonly DestinatariosDeAlertas $destinatarios,
        private readonly RegistroDeAlertas $registro,
    ) {}

    /**
     * Corre el proceso entero y devuelve lo que pasó.
     */
    public function correr(?CarbonInterface $hoy = null): ResumenDelProceso
    {
        $dia = Carbon::parse(($hoy ?? Carbon::today())->toDateString());
        $fechas = self::fechasQueLeTocanA($dia);
        $resumen = new ResumenDelProceso;

        foreach (self::empresasQueRecibenAlertas() as $empresa) {
            $resumen->contarEmpresa();

            try {
                $this->contexto->paraTenant(
                    $empresa,
                    fn () => $this->evaluarEmpresa($empresa, $fechas, $dia, $resumen)
                );
            } catch (\Throwable $error) {
                $resumen->contarEmpresaFallida($empresa, $error);
            }
        }

        return $resumen;
    }

    /**
     * Las fechas cuyas alertas salen hoy.
     *
     * Son la de hoy y las de los días no hábiles que vienen justo detrás,
     * porque a esas hay que adelantarlas (documento 2, 5.3): un viernes evalúa
     * viernes, sábado, domingo y también el lunes si es festivo.
     *
     * Si hoy **no** es hábil la lista viene vacía. No es un caso raro que
     * convenga tolerar: es la otra cara de la misma regla. Ninguna fecha se
     * corre a un domingo, así que ninguna alerta puede tocar un domingo, y un
     * proceso que corriera igualmente ese día mandaría el correo el día en que
     * la regla dice que no se manda.
     *
     * @return list<Carbon>
     */
    public static function fechasQueLeTocanA(CarbonInterface $hoy): array
    {
        $dia = Carbon::parse($hoy->toDateString());

        if (! MotorDeCalculo::esDiaHabilParaAlertas($dia)) {
            return [];
        }

        $fechas = [$dia->copy()];
        $siguiente = $dia->copy()->addDay();

        while (! MotorDeCalculo::esDiaHabilParaAlertas($siguiente)) {
            $fechas[] = $siguiente->copy();
            $siguiente->addDay();
        }

        return $fechas;
    }

    /**
     * Las empresas a las que el proceso les manda alertas.
     *
     * «Activas» se lee como el estado `activa` de `EstadoDeEmpresa`, que es el
     * único sentido que esa palabra tiene en el resto del código. Deja fuera a
     * las suspendidas —la suspensión es por mora (F5-04) y suspender no puede
     * seguir prestando el servicio que se dejó de pagar— y también al ambiente
     * de demostración, que nace `en_prueba` y es de F5-03.
     *
     * @return LazyCollection<int, Tenant>
     */
    public static function empresasQueRecibenAlertas(): LazyCollection
    {
        return Tenant::query()
            ->where('estado', EstadoDeEmpresa::Activa->value)
            ->orderBy('id')
            ->cursor();
    }

    /**
     * Una empresa, ya con su contexto fijado.
     *
     * @param  list<Carbon>  $fechas
     */
    private function evaluarEmpresa(Tenant $empresa, array $fechas, Carbon $hoy, ResumenDelProceso $resumen): void
    {
        foreach ($this->catalogo->reglas() as $regla) {
            $destinatarios = $this->destinatarios->para($empresa, $regla->tipo());

            // Sin nadie a quien mandársela no se registra ni se encola. Un
            // registro sin destinatarios no es evidencia de nada y además
            // quemaría la clave del día: la alerta quedaría marcada como ya
            // mandada sin que hubiera salido de aquí.
            if ($destinatarios === []) {
                $resumen->contarSinDestinatarios();

                continue;
            }

            foreach ($fechas as $fecha) {
                foreach ($regla->evaluar($fecha, $hoy) as $alerta) {
                    $this->registrarYEncolar($empresa, $alerta, $destinatarios, $resumen);
                }
            }
        }
    }

    /**
     * @param  list<string>  $destinatarios
     */
    private function registrarYEncolar(
        Tenant $empresa,
        AlertaPorEnviar $alerta,
        array $destinatarios,
        ResumenDelProceso $resumen,
    ): void {
        $registrada = $this->registro->registrar($alerta, $destinatarios);

        if (! $registrada instanceof AlertaEnviada) {
            $resumen->contarRepetida();

            return;
        }

        $resumen->contarRegistrada();

        EnviarAlerta::dispatch($registrada->getKey(), (int) $empresa->getKey());
    }
}
