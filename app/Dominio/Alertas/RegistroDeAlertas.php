<?php

namespace App\Dominio\Alertas;

use App\Models\AlertaEnviada;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * El sitio donde una alerta pasa de «hay que mandarla» a «está registrada».
 *
 * Es la mitad de la idempotencia que vive en el código, y consiste justamente
 * en **no comprobar nada**: no hay ningún `exists()` antes del insert. Se
 * intenta escribir y, si el índice único de `alertas_enviadas` la rechaza, es
 * que ya estaba y no hay nada que hacer.
 *
 * La diferencia con preguntar primero no es de estilo. Una comprobación previa
 * pierde la carrera: dos procesos que arranquen a la vez —un despliegue
 * mientras corre el programado, alguien que lo lanza a mano— preguntan los dos,
 * los dos ven que no está, y los dos insertan. El índice no pierde esa carrera
 * nunca, porque la resuelve la base de datos.
 *
 * ## Por qué el insert va en su propia transacción
 *
 * En PostgreSQL una sentencia que falla aborta la transacción en curso: todo lo
 * que venga después revienta hasta que alguien haga rollback. Como el proceso
 * diario registra muchas alertas seguidas y la segunda vez que corre **todas**
 * chocan con el índice, el choque tiene que quedar contenido. `DB::transaction`
 * anidada abre un savepoint y vuelve a él sola al fallar, así que el duplicado
 * se descarta sin arrastrar a los demás.
 */
class RegistroDeAlertas
{
    /**
     * Deja la alerta registrada como pendiente, o devuelve nulo si ya estaba.
     *
     * Nulo no es un error: es el caso normal de la segunda ejecución del día.
     *
     * @param  list<string>  $destinatarios
     */
    public function registrar(AlertaPorEnviar $alerta, array $destinatarios): ?AlertaEnviada
    {
        try {
            return DB::transaction(fn (): AlertaEnviada => AlertaEnviada::create([
                'vinculacion_id' => $alerta->vinculacion?->getKey(),
                'tipo' => $alerta->tipo,
                'clave' => $alerta->clave(),
                'fecha_programada' => $alerta->fechaDeEnvio,
                'fecha_del_hecho' => $alerta->fechaDelHecho,
                'asunto' => $alerta->asunto,
                'cuerpo' => $alerta->cuerpo,
                'destinatarios' => $destinatarios,
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
