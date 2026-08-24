<?php

namespace App\Proveedor;

use App\Presentacion\Formato;

/**
 * La escalera de mora del capítulo 6 del documento 4: «conviene definir la
 * escalera: aviso a los 5 días de mora, segundo aviso a los 15, suspensión a los
 * 30. Los números son una decisión de ustedes».
 *
 * Esta clase hace tres cosas y ninguna más:
 *
 * 1. Enseña los peldaños en la pantalla, para que la escalera acordada esté
 *    escrita donde se mira la mora y no solo en un archivo de configuración.
 * 2. Dice en qué peldaño está un cliente con tantos días de atraso.
 * 3. Redacta el aviso del prototipo: «Textiles Aurora lleva 22 días de mora.
 *    Según la escalera acordada, la suspensión es a los 30 días. Conviene
 *    llamar antes».
 *
 * **Lo que no hace es suspender.** Llegar al último peldaño pone el aviso; el
 * corte lo ejecuta el `owner` con su motivo escrito, porque el documento pide la
 * suspensión «con motivo y constancia» y un motivo no lo redacta un proceso
 * nocturno. Mientras los números sean la propuesta del documento y no una
 * decisión escrita —17.3 los sigue listando entre lo pendiente—, cortarle el
 * servicio solo a un cliente que paga sería tomarle a David una decisión que el
 * documento le atribuye a él.
 */
class EscaleraDeMora
{
    /**
     * Los peldaños, en orden, con la clave de `config/panel.php` de cada uno.
     *
     * @var array<string, string>
     */
    private const PELDANOS = [
        'primer_aviso' => 'Primer aviso',
        'segundo_aviso' => 'Segundo aviso',
        'suspension' => 'Suspensión',
    ];

    /**
     * Los peldaños para la tarjeta «Escalera de mora» de la pantalla.
     *
     * @return list<array{etiqueta: string, dias: int, texto: string}>
     */
    public static function peldanos(): array
    {
        $peldanos = [];

        foreach (self::PELDANOS as $clave => $etiqueta) {
            $dias = self::dias($clave);

            $peldanos[] = [
                'etiqueta' => $etiqueta,
                'dias' => $dias,
                'texto' => Formato::dias($dias),
            ];
        }

        return $peldanos;
    }

    /**
     * A cuántos días de mora se suspende, según la escalera acordada.
     */
    public static function diasParaSuspender(): int
    {
        return self::dias('suspension');
    }

    /**
     * Si con esos días de atraso ya le corresponde la suspensión.
     */
    public static function correspondeSuspender(int $diasDeAtraso): bool
    {
        return $diasDeAtraso >= self::diasParaSuspender();
    }

    /**
     * El aviso de la cabecera de la pantalla de Suscripciones, con el nombre de
     * la empresa que lleva más atraso.
     *
     * Se escribe entero en el servidor —incluido el «conviene llamar antes» del
     * prototipo— porque es la frase que decide si alguien coge el teléfono, y
     * armarla en la pantalla la dejaría fuera del alcance de cualquier prueba.
     */
    public static function aviso(string $empresa, int $diasDeAtraso): string
    {
        $suspension = self::diasParaSuspender();

        $frase = $empresa.' lleva '.Formato::dias($diasDeAtraso).' de mora. ';

        if (self::correspondeSuspender($diasDeAtraso)) {
            return $frase.'Según la escalera acordada, la suspensión es a los '
                .Formato::dias($suspension).': ya le corresponde. Suspender bloquea el acceso '
                .'y no borra nada, y reactivar lo devuelve todo donde estaba.';
        }

        return $frase.'Según la escalera acordada, la suspensión es a los '
            .Formato::dias($suspension).'. Conviene llamar antes.';
    }

    private static function dias(string $clave): int
    {
        return (int) config("panel.cobro.escalera_de_mora.{$clave}");
    }
}
