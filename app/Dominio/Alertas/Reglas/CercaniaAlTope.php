<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\HistorialDeContrato;
use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\AlertaPorEnviar;
use App\Dominio\Alertas\ReglaDeAlerta;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * «El contrato se acerca al tope de cuatro años» (documento 1, 9.4, cuarta
 * fila).
 *
 * Un solo aviso, tres meses antes del día en que el vínculo completaría los
 * cuatro años a término fijo. La anticipación es la decisión D.3, resuelta por
 * Angela, y vive en `config/alertas.php` porque es criterio y no ley: el día
 * que se quiera avisar con cuatro meses, se cambia ese renglón y nada más.
 *
 * ## Por qué no cuelga del vencimiento como las dos del preaviso
 *
 * `ReglaDePreavisoDelFijo` no le sirve. Las dos alertas del preaviso miran el
 * final del **tramo vigente**, que se mueve con cada prórroga; esta mira el
 * final del **vínculo**, que es una fecha fija desde el primer día:
 * `HistorialDeContrato::fechaDelTopeLegal()`, que ya trae aplicada la regla C.2
 * de la reforma —para los contratos anteriores al 25 de junio de 2025 los
 * cuatro años corren desde ese día y no desde la firma—.
 *
 * ## El tiempo que anuncia el mensaje
 *
 * «Lleva {tiempo}» se cuenta desde el inicio del conteo del tope y no desde la
 * firma, que es la misma convención que ya usa
 * `HistorialDeContrato::duracionTotalEnDias()`. En un contrato posterior a la
 * reforma las dos fechas son la misma; en uno anterior, los años previos a la
 * ley no cuentan para el tope, y son los años del tope los que este mensaje
 * está explicando.
 *
 * ## La fecha del tope va escrita, y no en cuenta atrás
 *
 * El texto de la sección 5.4 no trae ninguna referencia relativa que
 * `ReferenciaTemporal` pueda ajustar —no dice «en tres meses»—, así que la
 * fecha del tope se escribe entera y con año, como hace la del mes cumplido del
 * periodo de prueba: cae siempre a meses de distancia y muchas veces en el año
 * siguiente. Lo que sí se respeta es la regla de la sección 9.1: quien lee el
 * correo no tiene que calcular qué día es «el tope».
 */
class CercaniaAlTope implements ReglaDeAlerta
{
    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::CercaniaAlTope;
    }

    /**
     * @return iterable<AlertaPorEnviar>
     */
    public function evaluar(Carbon $fechaDelDisparo, Carbon $fechaDeEnvio): iterable
    {
        foreach ($this->candidatas() as $vinculacion) {
            $historial = HistorialDeContrato::deCargada($vinculacion);

            $tope = $historial->fechaDelTopeLegal();
            $inicio = $historial->inicioDelConteoDelTope();

            if (! $tope instanceof Carbon || ! $inicio instanceof Carbon) {
                continue;
            }

            $tope = Carbon::parse($tope->toDateString());

            if (! $this->disparoDe($tope)->isSameDay($fechaDelDisparo)) {
                continue;
            }

            $nombre = $vinculacion->persona->nombreCompleto();

            yield AlertaPorEnviar::paraEmpleado(
                $this->tipo(),
                $vinculacion,
                $fechaDeEnvio,
                $tope,
                'El contrato de '.$nombre.' se acerca al tope de cuatro años',
                $this->cuerpo($nombre, MotorDeCalculo::mesesCumplidos($inicio, $fechaDeEnvio), $tope),
            );
        }
    }

    private function cuerpo(string $nombre, int $meses, Carbon $tope): string
    {
        return 'El vínculo a término fijo de '.$nombre.' lleva '.Formato::tiempoTranscurrido($meses)
            .' y se acerca al límite legal de cuatro años, que se cumple el '.Formato::fechaLarga($tope)
            .'. Al superarlo, el contrato se convierte en indefinido por ley, sin necesidad de '
            .'liquidar prestaciones en ese momento.';
    }

    /**
     * El día en que toca avisar: la anticipación decidida, restada al tope.
     */
    private function disparoDe(Carbon $tope): Carbon
    {
        return $tope->copy()->subMonthsNoOverflow(
            (int) config('alertas.cercania_al_tope_meses')
        );
    }

    /**
     * Los contratos a término fijo activos de la empresa en contexto.
     *
     * No se acota por fechas y no es un descuido. El día del disparo sale del
     * inicio del vínculo, que puede ser el de la firma o el de la reforma según
     * C.2; una condición en SQL tendría que escoger entre los dos y sería la
     * misma regla escrita por segunda vez, en otro idioma y sin prueba. Quien
     * decide es la comparación, igual que en las dos alertas del preaviso.
     *
     * @return Collection<int, Vinculacion>
     */
    private function candidatas(): Collection
    {
        return Vinculacion::query()
            ->activas()
            ->with(['persona', 'periodos'])
            ->where('tipo_contrato', TipoContrato::Fijo->value)
            ->get();
    }
}
