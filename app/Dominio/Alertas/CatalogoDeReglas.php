<?php

namespace App\Dominio\Alertas;

/**
 * Las reglas de disparo que el proceso diario tiene que evaluar.
 *
 * Se registra como singleton en `AppServiceProvider`, y ahí es donde cada tarea
 * de la fase 3 suma la suya con una línea. Es a propósito la única lista: si el
 * proceso las instanciara él mismo, agregar una alerta obligaría a tocar el
 * proceso, y el proceso es lo que no queremos volver a tocar.
 *
 * **Hoy está vacía y no es un descuido.** F3-01 construye la infraestructura;
 * las once reglas llegan en F3-03, F3-04 y F3-05. Un proceso que corre sin
 * reglas registradas no manda nada, que es exactamente lo correcto.
 */
class CatalogoDeReglas
{
    /** @var list<ReglaDeAlerta> */
    private array $reglas = [];

    public function registrar(ReglaDeAlerta $regla): void
    {
        $this->reglas[] = $regla;
    }

    /**
     * @return list<ReglaDeAlerta>
     */
    public function reglas(): array
    {
        return $this->reglas;
    }
}
