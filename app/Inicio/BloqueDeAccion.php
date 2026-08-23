<?php

namespace App\Inicio;

use App\Autorizacion\Modulo;
use App\Autorizacion\PoliticaDeModulos;
use App\Contratacion\ListadoDeContratos;
use App\Contratacion\VigenciaDelContrato;
use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Listados\Listado;
use App\Models\User;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * El bloque «Requiere tu atención» del panel de inicio (documento 1, capítulo
 * 12, bloque 1 · documento 3, sección 5.2).
 *
 * Es lo primero que se ve al entrar, y responde «¿qué tengo que hacer hoy?»
 * antes de enseñar una sola estadística. Cada tarjeta lleva a un listado ya
 * filtrado donde se puede actuar; ninguna es informativa.
 *
 * ## Las cifras acumuladas de contratos son las del capítulo 12
 *
 * «Los que vencen en los próximos 30 días, en los próximos 60 y en los próximos
 * 90. El de 60 incluye a los de 30, y el de 90 incluye a ambos.» Eso es
 * literalmente lo que hace `ListadoDeContratos::porVencer()`, que cuenta desde
 * hoy hasta la fecha, y es la misma cuenta que la cabecera del módulo de
 * contratos ya enseñaba: el panel y el listado no pueden dar cifras distintas
 * porque solo existe una.
 *
 * ## Una tarjeta en cero no se pinta
 *
 * Es la misma decisión que tomó `ResumenMensual` con los correos: un bloque que
 * dice «0 contratos vencidos» en la sección llamada «Requiere tu atención»
 * enseña a no mirarla. Cuando no queda ninguna, el bloque lo dice con una frase
 * y no con cuatro ceros.
 *
 * ## Cada tarjeta se pinta solo si su destino se puede abrir
 *
 * Una tarjeta que lleva a un 403 no es una tarjeta, es una trampa. El módulo lo
 * decide `PoliticaDeModulos`, el mismo que responde en el middleware de la ruta
 * de destino, así que el panel y esa ruta no pueden discrepar. Es el criterio
 * que ya sigue `AlertasDelUsuario` con el panel de alertas.
 */
class BloqueDeAccion
{
    /**
     * Los rangos de la cifra acumulada de contratos por vencer, en días.
     *
     * El primero es el de la cifra grande y los demás van al pie. Salen de los
     * rangos que el filtro del listado ya ofrece, para que cada enlace caiga en
     * una opción real de la pantalla de destino y no en un número inventado.
     */
    private const RANGOS_POR_VENCER = [30, 60, 90];

    public function __construct(private readonly PoliticaDeModulos $politica) {}

    /**
     * Las tarjetas que este usuario tiene que ver hoy, salvo las que tardan.
     *
     * Las tres de aquí se resuelven con cuentas de SQL o sobre un puñado de
     * empleados, así que el panel abre de inmediato con ellas.
     *
     * @return list<array<string, mixed>>
     */
    public function paraElUsuario(User $usuario, ?Carbon $hoy = null): array
    {
        $hoy = self::dia($hoy);

        $tarjetas = [];

        if ($this->politica->puedeAcceder($usuario, Modulo::Contratos)) {
            $tarjetas[] = $this->contratosVencidos($hoy);
            $tarjetas[] = $this->contratosPorVencer($hoy);
        }

        if ($this->politica->puedeAcceder($usuario, Modulo::Empleados)) {
            $tarjetas[] = $this->periodosDePrueba($hoy);
        }

        return self::escritas($tarjetas);
    }

    /**
     * Las tarjetas que llegan después, cuando la pantalla ya se está viendo.
     *
     * Hoy es una: la de vacaciones acumuladas. **El saldo se calcula, no se
     * guarda** (regla 2), así que contar a quién le sobran quince días obliga a
     * pedírselo al motor empleado por empleado, y con doscientos eso son varios
     * segundos. Las tres salidas posibles eran leerlo de la caché —que no ofrece
     * el saldo de una persona justamente para no poder mandar sobre ella
     * (F4-02)—, tocar el motor para que corra más, que es donde se rompen los
     * números, o **no hacer esperar a nadie por ella**, que es esta.
     *
     * Va como prop diferida de Inertia: el panel se pinta con lo demás y la
     * tarjeta aparece sola en cuanto está lista, sin `axios` y sin que la
     * pantalla tenga que pedirla a mano.
     *
     * @return list<array<string, mixed>>
     */
    public function diferidasParaElUsuario(User $usuario, ?Carbon $hoy = null): array
    {
        if (! $this->politica->puedeAcceder($usuario, Modulo::Empleados)) {
            return [];
        }

        return self::escritas([$this->vacacionesAcumuladas(self::dia($hoy))]);
    }

    private static function dia(?Carbon $hoy): Carbon
    {
        return ($hoy ?? Carbon::today())->copy()->startOfDay();
    }

    /**
     * Las tarjetas que sí salen, escritas para la pantalla. Las que devolvieron
     * nulo son las que están en cero y no se pintan.
     *
     * @param  list<TarjetaDeAccion|null>  $tarjetas
     * @return list<array<string, mixed>>
     */
    private static function escritas(array $tarjetas): array
    {
        return array_values(array_map(
            fn (TarjetaDeAccion $tarjeta): array => $tarjeta->paraLaPantalla(),
            array_filter(
                $tarjetas,
                fn (?TarjetaDeAccion $tarjeta): bool => $tarjeta instanceof TarjetaDeAccion,
            ),
        ));
    }

    /**
     * Contratos cuya fecha pasó sin registrar prórroga ni terminación.
     *
     * Es la única tarjeta en rojo, y lo es porque aquí sí hay algo mal de
     * verdad: el contrato venció y nadie hizo nada. El destino pide
     * expresamente todos los vencimientos, porque el filtro del listado abre en
     * noventa días y los vencidos de hace medio año se quedarían fuera.
     */
    private function contratosVencidos(Carbon $hoy): ?TarjetaDeAccion
    {
        $cuantos = ListadoDeContratos::vencidosSinGestionar($hoy);

        if ($cuantos === 0) {
            return null;
        }

        return TarjetaDeAccion::de(
            valor: $cuantos,
            etiqueta: $cuantos === 1
                ? 'Contrato vencido sin gestionar'
                : 'Contratos vencidos sin gestionar',
            enlace: $this->enlaceDeContratos([
                'vence' => Listado::TODOS,
                'estado' => VigenciaDelContrato::VENCIDO,
            ]),
            accion: 'Ver y gestionar',
            tono: 'error',
        );
    }

    /**
     * Las tres cifras acumuladas de contratos por vencer.
     *
     * La tarjeta sale si hay algo en noventa días, que es el rango que incluye a
     * los tres: con dos vencimientos en el mes entrante y ninguno esta semana,
     * esconder la tarjeta por un cero en la cifra grande sería esconder lo que
     * la tarjeta existe para enseñar.
     */
    private function contratosPorVencer(Carbon $hoy): ?TarjetaDeAccion
    {
        $cuantos = [];

        foreach (self::RANGOS_POR_VENCER as $dias) {
            $cuantos[$dias] = ListadoDeContratos::porVencer($dias, $hoy);
        }

        [$primero, $segundo, $tercero] = self::RANGOS_POR_VENCER;

        if ($cuantos[$tercero] === 0) {
            return null;
        }

        return TarjetaDeAccion::de(
            valor: $cuantos[$primero],
            etiqueta: $cuantos[$primero] === 1
                ? 'Contrato vence en '.Formato::dias($primero)
                : 'Contratos vencen en '.Formato::dias($primero),
            enlace: $this->enlaceDeContratos(['vence' => (string) $primero]),
            accion: 'Ver y renovar',
            extras: [
                $this->extraPorVencer($cuantos[$segundo], $segundo),
                $this->extraPorVencer($cuantos[$tercero], $tercero),
            ],
        );
    }

    /**
     * @return array{texto: string, enlace: string}
     */
    private function extraPorVencer(int $cuantos, int $dias): array
    {
        return [
            'texto' => $cuantos.' en '.Formato::dias($dias),
            'enlace' => $this->enlaceDeContratos(['vence' => (string) $dias]),
        ];
    }

    private function periodosDePrueba(Carbon $hoy): ?TarjetaDeAccion
    {
        $cuantos = AtencionDelInicio::PeriodosDePrueba->cuantos($hoy);

        if ($cuantos === 0) {
            return null;
        }

        return TarjetaDeAccion::de(
            valor: $cuantos,
            etiqueta: $cuantos === 1
                ? 'Periodo de prueba termina esta semana'
                : 'Periodos de prueba terminan esta semana',
            enlace: $this->enlaceDeEmpleados(AtencionDelInicio::PeriodosDePrueba),
            accion: 'Ver empleados',
        );
    }

    /**
     * Las personas que ya tienen acumulado un periodo completo de vacaciones.
     *
     * La etiqueta dice lo que la tarjeta cuenta y no «vacaciones en riesgo»,
     * que en el reporte de 11.4 son dos poblaciones: esta y la de quien está por
     * cumplir un año sin haber disfrutado los seis días continuos. La segunda
     * tiene su propio aviso —el correo mensual `AnioSinDisfruteMinimo`— y no se
     * suma aquí, así que llamarlas a las dos por el nombre del reporte diría un
     * número que no es el del reporte.
     */
    private function vacacionesAcumuladas(Carbon $hoy): ?TarjetaDeAccion
    {
        $cuantos = AtencionDelInicio::VacacionesAcumuladas->cuantos($hoy);

        if ($cuantos === 0) {
            return null;
        }

        $periodo = Formato::diasDecimales(SaldoDeVacaciones::diasDeUnPeriodoCompleto());

        return TarjetaDeAccion::de(
            valor: $cuantos,
            etiqueta: $cuantos === 1
                ? 'Persona con '.$periodo.' o más de vacaciones acumuladas'
                : 'Personas con '.$periodo.' o más de vacaciones acumuladas',
            enlace: $this->enlaceDeEmpleados(AtencionDelInicio::VacacionesAcumuladas),
            accion: 'Ver empleados',
        );
    }

    /**
     * @param  array<string, string>  $filtros
     */
    private function enlaceDeContratos(array $filtros): string
    {
        return Modulo::Contratos->url().'?'.http_build_query($filtros);
    }

    private function enlaceDeEmpleados(AtencionDelInicio $atencion): string
    {
        return Modulo::Empleados->url().'?'.http_build_query(['atencion' => $atencion->value]);
    }
}
