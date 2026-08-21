<?php

namespace App\Proveedor;

use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * Las constantes legales del sistema, tal como se leen en el panel del
 * proveedor (documento 4, capítulo 8).
 *
 * **Los valores no viven aquí.** Viven donde han vivido siempre, en
 * `config/reglas_laborales.php`, que es lo que exige
 * `.claude/rules/20-calculo-y-reglas-legales.md`: un solo sitio para los números
 * que producen cuentas iguales en todos los clientes a la vez. Esta clase lee
 * ese archivo y le pone al lado lo que la pantalla necesita y un archivo de
 * configuración no puede dar: **el título con el que se lee la constante, la
 * norma que la respalda y la fecha en que se verificó por última vez**.
 *
 * En el archivo de configuración esas tres cosas están escritas en comentarios,
 * que sirven a quien abre el código y no a quien abre el panel. Aquí están
 * escritas como declaración, para que la pantalla pueda enseñarlas. Si una norma
 * cambia, se cambia el valor allá y la norma y la fecha aquí: **son el mismo
 * cambio y ninguna de las dos mitades sirve sin la otra**.
 *
 * La pantalla es de solo lectura y eso es una decisión, no una tarea a medias:
 * el prototipo la describe como «donde se ajusta el sistema cuando cambia una
 * ley», pero la regla del proyecto dice que estos números viven en
 * configuración. Editarlos desde una pantalla los sacaría de allí, dejaría el
 * archivo mintiendo y quitaría del camino la revisión que hoy pasa cualquier
 * cambio de un número legal. Está anotado en `ESTADO.md` para David.
 */
final class ConstantesLegales
{
    /**
     * Lo que se enseña al pie de la tabla. Es la promesa de la pantalla y por
     * eso la escribe el servidor, que es quien la cumple.
     */
    public const SOLO_LECTURA = 'Estos valores se leen de la configuración del sistema y no se '
        .'editan desde aquí: un número legal equivocado calcula mal en todas las empresas a la vez, '
        .'así que cambiarlo pasa por una revisión y un despliegue. La columna «Verificada» dice '
        .'cuándo se contrastó cada uno contra su norma por última vez.';

    /**
     * Cada constante con su clave en `config/reglas_laborales.php`, cómo se
     * llama en pantalla, cómo se escribe su valor, su norma y su verificación.
     *
     * @var list<array{clave: string, titulo: string, unidad: string, norma: string, verificada: string}>
     */
    private const DECLARADAS = [
        [
            'clave' => 'dias_vacaciones_por_mes',
            'titulo' => 'Acumulación de vacaciones por mes',
            'unidad' => 'dias',
            'norma' => 'Art. 186 CST — 15 días hábiles por año',
            'verificada' => '2026-08-17',
        ],
        [
            'clave' => 'periodo_prueba_indefinido_meses',
            'titulo' => 'Periodo de prueba en contrato indefinido',
            'unidad' => 'meses',
            'norma' => 'Art. 78 CST',
            'verificada' => '2026-08-17',
        ],
        [
            'clave' => 'periodo_prueba_fijo_divisor',
            'titulo' => 'Periodo de prueba en fijo inferior a un año',
            'unidad' => 'fraccion',
            'norma' => 'Art. 78 CST — la quinta parte del término pactado',
            'verificada' => '2026-08-17',
        ],
        [
            'clave' => 'periodo_prueba_fijo_tope_meses',
            'titulo' => 'Tope del periodo de prueba en fijo',
            'unidad' => 'meses',
            'norma' => 'Art. 78 CST — máximo legal absoluto',
            'verificada' => '2026-08-17',
        ],
        [
            'clave' => 'preaviso_dias',
            'titulo' => 'Preaviso de no prórroga',
            'unidad' => 'dias',
            'norma' => 'Art. 46 CST, mod. Ley 2466 de 2025',
            'verificada' => '2026-08-17',
        ],
        [
            'clave' => 'tope_termino_fijo_anios',
            'titulo' => 'Tope del contrato a término fijo',
            'unidad' => 'anios',
            'norma' => 'Art. 46 CST, reformado por la Ley 2466 de 2025',
            'verificada' => '2026-08-17',
        ],
        [
            'clave' => 'disfrute_minimo_dias_habiles',
            'titulo' => 'Disfrute mínimo anual de vacaciones',
            'unidad' => 'dias_habiles',
            'norma' => 'Art. 190 CST — días hábiles continuos',
            'verificada' => '2026-08-17',
        ],
        [
            'clave' => 'prorrogas_cortas_pactadas',
            'titulo' => 'Prórrogas cortas pactadas antes del mínimo de un año',
            'unidad' => 'veces',
            'norma' => 'Concepto MinTrabajo 6157 de 2026',
            'verificada' => '2026-08-17',
        ],
        [
            'clave' => 'prorrogas_cortas_automaticas',
            'titulo' => 'Prórrogas cortas automáticas por silencio',
            'unidad' => 'veces',
            'norma' => 'Concepto MinTrabajo 6157 de 2026',
            'verificada' => '2026-08-17',
        ],
        [
            'clave' => 'vigencia_reforma_termino_fijo',
            'titulo' => 'Entrada en vigencia del régimen del término fijo',
            'unidad' => 'fecha',
            'norma' => 'Ley 2466 de 2025 — de esa fecha corren los cuatro años en los contratos que ya estaban vigentes',
            'verificada' => '2026-08-18',
        ],
    ];

    /**
     * Las filas de la tabla de la pantalla.
     *
     * @return list<array{constante: string, valor: string, norma: string, verificada: string}>
     */
    public static function filas(): array
    {
        return array_map(fn (array $constante): array => [
            'constante' => $constante['titulo'],
            'valor' => self::valor($constante['clave'], $constante['unidad']),
            'norma' => $constante['norma'],
            'verificada' => Formato::fecha(Carbon::parse($constante['verificada'])),
        ], self::DECLARADAS);
    }

    /**
     * El valor de la configuración, escrito como se lee.
     *
     * Cada unidad se escribe distinto y ninguna se escribe a mano: los días
     * pasan por `Formato::dias()`, que es el único sitio del sistema donde se
     * decide si lleva singular o plural.
     */
    private static function valor(string $clave, string $unidad): string
    {
        /** @var mixed $valor */
        $valor = config('reglas_laborales.'.$clave);

        return match ($unidad) {
            'dias' => self::conDecimales($valor).' días',
            'dias_habiles' => Formato::dias((int) $valor).' hábiles',
            'meses' => (int) $valor === 1 ? '1 mes' : $valor.' meses',
            'anios' => (int) $valor === 1 ? '1 año' : $valor.' años',
            'veces' => (int) $valor === 1 ? '1 vez' : $valor.' veces',
            'fraccion' => '1/'.$valor,
            'fecha' => Formato::fecha(Carbon::parse((string) $valor)),
            default => (string) $valor,
        };
    }

    /**
     * 1,25 y no 1.25: el separador decimal en Colombia es la coma.
     */
    private static function conDecimales(mixed $valor): string
    {
        $numero = (float) $valor;

        return number_format($numero, fmod($numero, 1.0) === 0.0 ? 0 : 2, ',', '.');
    }
}
