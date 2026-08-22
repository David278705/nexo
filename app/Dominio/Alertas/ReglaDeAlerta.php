<?php

namespace App\Dominio\Alertas;

use Illuminate\Support\Carbon;

/**
 * Una regla de disparo: a quién y cuándo le toca una alerta.
 *
 * Es el único punto de extensión del motor. F3-03 trae las del periodo de
 * prueba, F3-04 las del término fijo y F3-05 los resúmenes mensuales; ninguna
 * de las tres toca el proceso diario, la idempotencia ni el envío, porque todo
 * eso ya está escrito una vez en F3-01.
 *
 * ## El contrato
 *
 * El proceso llama a `evaluar()` una vez por cada fecha que le toca al día de
 * hoy —la de hoy y las de los días no hábiles que vienen detrás, porque esas
 * hay que adelantarlas—. La regla recibe las dos fechas ya resueltas:
 *
 * - `$fechaDelDisparo` es el día en que la alerta **tocaría** naturalmente. Es
 *   contra esta contra la que la regla busca a sus empleados.
 * - `$fechaDeEnvio` es el día en que el correo va a salir de verdad, que es hoy.
 *
 * La regla no compara las dos a mano: le pasa las dos a
 * `ReferenciaTemporal::hasta()` y esa escribe la frase con la fecha entre
 * paréntesis —«mañana (14 de junio)», «en dos días (14 de junio)»— ya ajustada
 * al adelanto (documento 1, 9.1). Es la única forma de que las once alertas
 * digan lo mismo el día en que un festivo produzca un adelanto raro.
 *
 * ## Lo que la regla no tiene que hacer
 *
 * No mira si ya se envió —eso lo resuelve el índice único—, no busca
 * destinatarios —eso lo resuelve `DestinatariosDeAlertas` con el módulo del
 * tipo— y **no filtra por empresa**: cuando la llaman, la empresa ya está en
 * contexto y el global scope hace el resto.
 *
 * Lo que sí es suyo: no devolver nada de un empleado inactivo. Es la primera
 * regla transversal de la sección 9.1 y se comprueba con
 * `Vinculacion::scopeActivas()`.
 */
interface ReglaDeAlerta
{
    /**
     * De qué alerta del catálogo es esta regla.
     */
    public function tipo(): TipoDeAlerta;

    /**
     * Las alertas que corresponde mandar hoy por el disparo de esa fecha.
     *
     * @return iterable<AlertaPorEnviar>
     */
    public function evaluar(Carbon $fechaDelDisparo, Carbon $fechaDeEnvio): iterable;
}
