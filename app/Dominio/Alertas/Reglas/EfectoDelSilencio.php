<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\HistorialDeContrato;
use App\Dominio\Alertas\ReferenciaTemporal;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * «Último día para avisar la no renovación» — 30 días antes del vencimiento
 * (documento 1, 9.4, segunda fila).
 *
 * Es la alerta de mayor consecuencia jurídica del producto: el día que sale es
 * el último en que la empresa puede evitar que el contrato se prorrogue solo.
 * A partir de mañana, callar ya prorroga.
 *
 * Dispara el mismo día de la fecha límite del preaviso, así que su
 * `fechaDelHecho` y su fecha de disparo son la misma y la frase dice «hoy». Con
 * el corrimiento al día hábil anterior deja de decirlo —«en dos días (1 de
 * diciembre)»— y eso no es un defecto: el correo sale antes porque el día del
 * plazo es domingo, y quien lo lee tiene que saber cuál es el día de verdad.
 *
 * El cuerpo no nombra el cargo; el de la sección 5.4 tampoco. Aquí la
 * consecuencia es del contrato y no de quién lo ocupa.
 *
 * ## Tres consecuencias distintas, según lo que quepa antes del tope
 *
 * La primera frase es siempre la misma; la segunda —qué pasa si nadie avisa—
 * depende de cuánto falte para los cuatro años, y son las tres variantes de la
 * decisión D.3 del documento 6. El artículo 46 del CST reformado resuelve el
 * caso solo: la prórroga por silencio es «por un término igual al inicialmente
 * pactado… sin que pueda superarse el límite máximo de cuatro (4) años», de
 * modo que **se trunca sola** y nunca se pasa.
 *
 * | Situación | Qué dice |
 * |---|---|
 * | El vencimiento ya está en el tope, o después | No cabe ninguna prórroga: el contrato se vuelve indefinido. |
 * | La prórroga completa se pasaría del tope | Queda prorrogado **solo hasta** el tope, y ahí se vuelve indefinido. |
 * | La prórroga completa cabe | El caso normal, el texto literal de la sección 5.4. |
 *
 * Las tres se deciden comparando dos fechas que responde el historial —el tope
 * y hasta dónde llegaría la prórroga automática— y ninguna aproxima nada. D.3
 * dice «llega exacto (o prácticamente) al tope», y ese «prácticamente» no se
 * implementa: un margen de tolerancia sería un umbral legal inventado. Una
 * prórroga que cabe entera, aunque termine dos días antes del tope, recibe el
 * texto normal, y es cierto: se prorroga por el término completo.
 */
class EfectoDelSilencio extends ReglaDePreavisoDelFijo
{
    /**
     * El caso normal, que es el texto literal de la sección 5.4: todavía queda
     * sitio antes del tope para la prórroga entera.
     */
    private const PRORROGA_COMPLETA = 'Si no se avisa, el contrato queda prorrogado automáticamente '
        .'por un término igual al último pactado.';

    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::EfectoDelSilencio;
    }

    protected function diasAntesDelPreaviso(): int
    {
        return 0;
    }

    protected function asunto(string $nombre): string
    {
        return 'Último día para avisar la no renovación de '.$nombre;
    }

    protected function cuerpo(
        string $nombre,
        string $cargo,
        Carbon $vencimiento,
        ReferenciaTemporal $referencia,
        HistorialDeContrato $historial,
    ): string {
        return $referencia->fraseCapitalizada().' es el último día para notificar a '.$nombre
            .' que su contrato no se renovará. '.$this->consecuenciaDelSilencio($vencimiento, $historial);
    }

    /**
     * Qué pasa si nadie avisa: las tres variantes de la decisión D.3.
     *
     * El «hoy (fecha)» que D.3 escribe al principio de la variante truncada no
     * se repite aquí: ya lo dijo la primera frase del mensaje, y decir la misma
     * fecha dos veces en dos renglones es lo que hace que un correo se lea como
     * una plantilla mal armada. Lo que sí se copia literal es la consecuencia,
     * que es la parte que tiene efecto jurídico.
     */
    private function consecuenciaDelSilencio(Carbon $vencimiento, HistorialDeContrato $historial): string
    {
        $tope = $historial->fechaDelTopeLegal();
        // La duración real de la prórroga automática (PROD-33): la del último
        // tramo, o un año cuando la ley lo exige.
        $prorroga = $historial->finDeLaProximaAutomaticaSinTope();

        if (! $tope instanceof Carbon || ! $prorroga instanceof Carbon) {
            return self::PRORROGA_COMPLETA;
        }

        if ($tope->lessThanOrEqualTo($vencimiento)) {
            return 'Si no se avisa, el contrato ya no puede prorrogarse: el vínculo cumple el tope '
                .'legal de cuatro años el '.Formato::fechaLarga($tope)
                .' y a partir de esa fecha se convierte en indefinido por ley.';
        }

        if ($prorroga->greaterThan($tope)) {
            return 'Si no se avisa, el contrato queda prorrogado automáticamente solo hasta el '
                .Formato::fechaLarga($tope)
                .', y al llegar a esa fecha se convierte en indefinido por ley.';
        }

        if ($historial->laProximaAutomaticaEsDeUnAnio()) {
            return 'Si no se avisa, el contrato queda prorrogado automáticamente por un año, hasta el '
                .Formato::fechaLarga($prorroga).'.';
        }

        return 'Si no se avisa, el contrato queda prorrogado automáticamente por un término igual al '
            .'último pactado, hasta el '.Formato::fechaLarga($prorroga).'.';
    }
}
