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
 * Trae las trece del capítulo 9 del documento 1: las cinco del periodo de prueba
 * (F3-03), las cuatro del término fijo (F3-04) y los cuatro resúmenes agrupados
 * —tres mensuales y el bimestral de obra o labor— (F3-05). Un proceso que corre
 * sin reglas registradas no manda nada, que es exactamente lo correcto, y por
 * eso las pruebas del armazón vacían el catálogo antes de meter la suya.
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
