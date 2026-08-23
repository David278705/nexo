<?php

namespace App\Inicio\Indicadores;

use App\Autorizacion\Modulo;
use App\Autorizacion\PoliticaDeModulos;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Los seis bloques de indicadores del panel de inicio (documento 1, 12.1,
 * bloques 2 a 7 — el bloque 1, «Requiere tu atención», es `BloqueDeAccion`,
 * de F4-03).
 *
 * Cada bloque solo se calcula y se enseña si el usuario tiene el módulo del
 * que salen sus números: es la misma regla que ya sigue `BloqueDeAccion` con
 * sus tarjetas, para que nadie reciba en el panel un dato de un módulo al que
 * no puede entrar. El bloque de ausentismo se arma con dos mitades
 * independientes porque sus indicadores vienen de dos módulos distintos —
 * Incapacidades y Licencias— y puede enseñar una sin la otra.
 *
 * Todo esto llega en una sola prop diferida de Inertia (`indicadores`), igual
 * que la tarjeta de vacaciones acumuladas de F4-03: ninguno de los seis
 * bloques recorre la planta persona por persona salvo el de vacaciones, que
 * lee de la caché de saldos y no del motor, así que el panel puede completarse
 * dentro del umbral de dos segundos sin bloquear la primera pintura.
 *
 * Cada bloque tiene la misma forma —`titulo`, `tarjetas` y `distribuciones`,
 * y opcionalmente `avisos`—, así que la pantalla los pinta con un solo bucle
 * en lugar de repetir marcado por bloque.
 */
class BloqueDeIndicadores
{
    public function __construct(private readonly PoliticaDeModulos $politica) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function paraElUsuario(User $usuario, ?Carbon $hoy = null): array
    {
        $hoy = ($hoy ?? Carbon::today())->copy()->startOfDay();
        $bloques = [];

        if ($this->politica->puedeAcceder($usuario, Modulo::Vacaciones)) {
            $bloques[] = IndicadoresDeVacaciones::paraLaEmpresa($hoy);
        }

        if ($this->politica->puedeAcceder($usuario, Modulo::Empleados)) {
            $bloques[] = IndicadoresDePlanta::paraLaEmpresa($hoy);
        }

        if ($this->politica->puedeAcceder($usuario, Modulo::Terminaciones)) {
            $bloques[] = IndicadoresDeRotacion::paraLaEmpresa($hoy);
        }

        $ausentismo = $this->ausentismo($usuario, $hoy);

        if ($ausentismo !== null) {
            $bloques[] = $ausentismo;
        }

        if ($this->politica->puedeAcceder($usuario, Modulo::Empleados)) {
            $bloques[] = IndicadoresDeComposicion::paraLaEmpresa($hoy);
        }

        if ($this->politica->puedeAcceder($usuario, Modulo::Movimientos)) {
            $bloques[] = IndicadoresDeMovimientos::paraLaEmpresa($hoy);
        }

        return $bloques;
    }

    /**
     * El bloque de ausentismo, armado con la mitad de Incapacidades, la de
     * Licencias, las dos o ninguna, según lo que el usuario tenga.
     *
     * @return array<string, mixed>|null
     */
    private function ausentismo(User $usuario, Carbon $hoy): ?array
    {
        $tarjetas = [];
        $distribuciones = [];

        if ($this->politica->puedeAcceder($usuario, Modulo::Incapacidades)) {
            $porIncapacidad = IndicadoresDeAusentismo::porIncapacidad($hoy);
            $tarjetas = [...$tarjetas, ...$porIncapacidad['tarjetas']];
            $distribuciones = [...$distribuciones, ...$porIncapacidad['distribuciones']];
        }

        if ($this->politica->puedeAcceder($usuario, Modulo::Licencias)) {
            $porAusencias = IndicadoresDeAusentismo::porAusencias($hoy);
            $tarjetas = [...$tarjetas, ...$porAusencias['tarjetas']];
            $distribuciones = [...$distribuciones, ...$porAusencias['distribuciones']];
        }

        if ($tarjetas === [] && $distribuciones === []) {
            return null;
        }

        return ['titulo' => 'Ausentismo', 'tarjetas' => $tarjetas, 'distribuciones' => $distribuciones];
    }
}
