<?php

namespace App\Contratacion;

/**
 * Los textos de la fecha de finalización estimada de la obra o labor (`OL-01`).
 *
 * Decidido por David el 2026-11-02: la obra o labor maneja periodo de prueba,
 * calculado con las reglas del término fijo sobre una fecha que la empresa
 * estima. El riesgo es que esa fecha no se parezca a la obra real: si la obra
 * termina antes, la quinta parte calculada sobre la estimación excede la que
 * la ley permitía, y ese periodo de prueba no sería válido.
 *
 * Por eso los textos viven aquí y no en el componente Vue: son la parte de la
 * funcionalidad que traslada la responsabilidad al cliente, y una prueba tiene
 * que poder comprobar que siguen diciendo lo que dicen. Los usan el asistente
 * de registro, la ficha del empleado y los correos de alerta.
 */
final class FechaFinEstimada
{
    /**
     * El título del aviso que acompaña al registro de una obra o labor.
     */
    public const TITULO = 'Está registrando un contrato de obra o labor';

    /**
     * Qué hace el sistema con la fecha estimada.
     */
    public const EXPLICACION = 'Puede registrar una fecha estimada de finalización de la obra. Si la registra, '
        .'el sistema calcula un periodo de prueba sugerido con las mismas reglas del contrato a término fijo: '
        .'la quinta parte de la duración estimada, sin pasar de dos meses. Si no la registra, el sistema no '
        .'calcula periodo de prueba ni envía alertas sobre él.';

    /**
     * El riesgo, dicho sin rodeos.
     */
    public const RIESGO = 'El cálculo depende por completo de la fecha que usted registre, y el sistema no '
        .'tiene cómo comprobar que corresponda a la duración real de la obra. Regístrela lo más ajustada a la '
        .'realidad posible: si la obra termina antes de lo estimado, el periodo de prueba calculado puede ser '
        .'mayor al que la ley permite y no ser válido. Verificar que el periodo de prueba sea legal y que esté '
        .'pactado por escrito le corresponde a la empresa.';

    /**
     * La nota corta que acompaña al periodo de prueba en la ficha y en los
     * correos de alerta, para que nadie lo lea como un plazo legal confirmado.
     */
    public const NOTA = 'Este periodo de prueba es una sugerencia calculada sobre la fecha estimada de '
        .'finalización que registró la empresa. Verifique que esa fecha corresponda a la duración real de la obra.';

    /**
     * Todo el aviso, como lo recibe el asistente.
     *
     * @return array{titulo: string, explicacion: string, riesgo: string}
     */
    public static function aviso(): array
    {
        return [
            'titulo' => self::TITULO,
            'explicacion' => self::EXPLICACION,
            'riesgo' => self::RIESGO,
        ];
    }
}
