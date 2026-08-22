<?php

namespace App\Dominio\Alertas;

/**
 * En qué punto de su viaje está una alerta ya registrada.
 *
 * Son tres y no dos porque el registro se escribe **antes** de enviar: primero
 * queda la fila —que es lo que impide el duplicado— y después el trabajador de
 * la cola la manda. Entre esas dos cosas la alerta existe y todavía no salió, y
 * ese hueco tiene nombre: `pendiente`.
 *
 * `fallida` solo se escribe cuando la cola agotó sus reintentos. Un fallo
 * intermedio deja la alerta pendiente y con un intento más, que es lo que
 * permite responder «se intentó tres veces y el proveedor rechazó» en la
 * consola del proveedor (F5-05).
 */
enum EstadoDeAlerta: string
{
    case Pendiente = 'pendiente';

    case Enviada = 'enviada';

    case Fallida = 'fallida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de envío',
            self::Enviada => 'Enviada',
            self::Fallida => 'Fallida',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Pendiente => 'neutro',
            self::Enviada => 'exito',
            self::Fallida => 'error',
        };
    }
}
