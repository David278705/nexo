<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\HistorialDeContrato;
use App\Dominio\Alertas\ReferenciaTemporal;
use App\Dominio\Alertas\TipoDeAlerta;
use Illuminate\Support\Carbon;

/**
 * «Última renovación corta posible» (documento 1, 9.4, tercera fila).
 *
 * Avisa que el contrato agotó las tres prórrogas inferiores a un año que admite
 * el artículo 46, así que la siguiente ya no puede serlo. Es la última de las
 * cuatro alertas del término fijo, y la que más tarde llegó: su día de disparo
 * dependía de una pregunta que Angela respondió en agosto de 2026.
 *
 * ## Es un recordatorio, no un acuse de recibo
 *
 * No sale el día en que se registra la prórroga que agota el cupo —ese día la
 * empresa ya lo sabe, porque el modal de renovación se lo dijo en ámbar— sino
 * **32 días antes del vencimiento del tramo que lo agotó**, que es el mismo
 * momento en que sale «plazo para definir la renovación». Llega acompañada de
 * ella a propósito: el día en que hay que decidir la renovación es el día en
 * que importa saber que la próxima debe ser de un año.
 *
 * ## A quién le toca
 *
 * Al contrato cuyo **tramo vigente** es una prórroga corta y detrás de la cual
 * ya no queda cupo —`HistorialDeContrato::elTramoVigenteAgotoElCupoCorto()`—.
 * Es la lectura literal de «el tramo que agota el cupo»: si después la empresa
 * renueva por un año, el vigente deja de ser una prórroga corta y el aviso no
 * se repite, que es lo correcto, porque ya hizo lo que el aviso le pedía.
 *
 * El cupo es **uno solo, de tres, para toda la cadena** (documento 6, C.1,
 * corregida por Angela): dos prórrogas pactadas cortas y una automática lo
 * agotan igual que tres pactadas, y este aviso sale en los dos casos.
 *
 * ## Por qué el mensaje no lleva fecha
 *
 * El texto de la sección 5.4 no nombra ningún día, y por eso esta es la única
 * de las tres del preaviso que no usa `ReferenciaTemporal`. No hace falta: lo
 * que anuncia no vence en una fecha, vale de la próxima renovación en adelante.
 * El día del vencimiento lo trae el correo hermano, que sale el mismo día.
 */
class UltimaRenovacionCorta extends ReglaDePreavisoDelFijo
{
    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::UltimaRenovacionCorta;
    }

    protected function diasAntesDelPreaviso(): int
    {
        return self::DIAS_DE_MARGEN_DEL_PLAZO;
    }

    protected function aplicaA(HistorialDeContrato $historial): bool
    {
        return $historial->elTramoVigenteAgotoElCupoCorto();
    }

    /**
     * El día del que habla el mensaje es el vencimiento del tramo, no la fecha
     * límite del preaviso: lo que el aviso explica es qué puede pactarse a
     * partir de ahí, y el plazo de los treinta días no tiene nada que ver.
     */
    protected function fechaDelHecho(Carbon $vencimiento, Carbon $limite): Carbon
    {
        return $vencimiento;
    }

    protected function asunto(string $nombre): string
    {
        return 'La próxima renovación de '.$nombre.' debe ser de un año';
    }

    /**
     * El texto de la sección 5.4, con dos palabras de precisión en la cuenta.
     *
     * La plantilla dice «acumula {n} prórrogas», y el número que hace falta es
     * el de las **cortas**, que son las que gastan cupo. En el contrato normal
     * los dos números son el mismo; en uno que además tuvo una prórroga larga
     * en medio, decir el total dejaría al lector sin entender por qué la
     * siguiente ya no puede ser corta. La segunda frase va literal: es la que
     * tiene efecto jurídico.
     */
    protected function cuerpo(
        string $nombre,
        string $cargo,
        Carbon $vencimiento,
        ReferenciaTemporal $referencia,
        HistorialDeContrato $historial,
    ): string {
        return 'El contrato de '.$nombre.' acumula '.$historial->prorrogasCortas()
            .' prórrogas inferiores a un año. La próxima renovación deberá ser de mínimo un año.';
    }
}
