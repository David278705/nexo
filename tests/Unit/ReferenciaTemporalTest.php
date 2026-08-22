<?php

use App\Dominio\Alertas\ReferenciaTemporal;
use Illuminate\Support\Carbon;

/**
 * Cómo se escribe la referencia temporal de una alerta (F3-02).
 *
 * Es la mitad que le faltaba al corrimiento al día hábil anterior. La otra
 * mitad —adelantar la evaluación y guardar la fecha corrida— la trajo F3-01;
 * esto es lo que hace que el texto no mienta cuando el correo sale antes de lo
 * que tocaba: si decía «mañana» y se adelantó dos días, tiene que decir «en dos
 * días» (documento 1, 9.1).
 *
 * Se prueba aparte de las alertas porque las once lo van a compartir. Escribir
 * la frase once veces sería garantizar que una de las once quede mal el día en
 * que Colombia mueva un festivo.
 */
function referenciaTemporalDe(string $envio, string $hecho): ReferenciaTemporal
{
    return ReferenciaTemporal::hasta(Carbon::parse($envio), Carbon::parse($hecho));
}

describe('la frase que se mete en el mensaje', function () {
    it('dice «hoy» cuando el correo sale el mismo día del hecho', function () {
        expect(referenciaTemporalDe('2026-08-21', '2026-08-21')->frase())->toBe('hoy (21 de agosto)');
    });

    it('dice «mañana» cuando falta un día', function () {
        expect(referenciaTemporalDe('2026-08-20', '2026-08-21')->frase())->toBe('mañana (21 de agosto)');
    });

    it('dice «en dos días» cuando faltan dos', function () {
        expect(referenciaTemporalDe('2026-08-19', '2026-08-21')->frase())->toBe('en dos días (21 de agosto)');
    });

    it('escribe con letras las cantidades de una cifra', function () {
        expect(referenciaTemporalDe('2026-08-18', '2026-08-21')->frase())->toBe('en tres días (21 de agosto)')
            ->and(referenciaTemporalDe('2026-08-17', '2026-08-21')->frase())->toBe('en cuatro días (21 de agosto)')
            ->and(referenciaTemporalDe('2026-08-12', '2026-08-21')->frase())->toBe('en nueve días (21 de agosto)');
    });

    it('escribe con número las de dos cifras, como el aviso de los 15 días', function () {
        // El caso del documento 2, 5.4: quien ingresa el 15 de abril cumple 45
        // días el 30 de mayo y el periodo de prueba le termina el 14 de junio.
        expect(referenciaTemporalDe('2026-05-30', '2026-06-14')->frase())->toBe('en 15 días (14 de junio)')
            ->and(referenciaTemporalDe('2026-08-11', '2026-08-21')->frase())->toBe('en 10 días (21 de agosto)');
    });

    it('lleva siempre la fecha exacta entre paréntesis, para que nadie tenga que calcular', function () {
        expect(referenciaTemporalDe('2026-12-24', '2026-12-25')->frase())->toBe('mañana (25 de diciembre)');
    });
});

describe('la frase que empieza una oración', function () {
    it('va en mayúscula inicial', function () {
        expect(referenciaTemporalDe('2026-08-20', '2026-08-21')->fraseCapitalizada())->toBe('Mañana (21 de agosto)')
            ->and(referenciaTemporalDe('2026-08-19', '2026-08-21')->fraseCapitalizada())->toBe('En dos días (21 de agosto)')
            ->and(referenciaTemporalDe('2026-08-21', '2026-08-21')->fraseCapitalizada())->toBe('Hoy (21 de agosto)');
    });
});

describe('los días que faltan', function () {
    it('son la diferencia entre las dos fechas y no los días corridos', function () {
        // Del viernes al domingo faltan dos días, aunque sean tres días corridos.
        expect(referenciaTemporalDe('2026-08-21', '2026-08-23')->dias)->toBe(2);
    });

    it('no acepta un hecho anterior al envío, porque ninguna alerta habla del pasado en relativo', function () {
        expect(fn () => referenciaTemporalDe('2026-08-21', '2026-08-20'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('el corrimiento al día hábil anterior', function () {
    it('el aviso de la víspera que se adelanta al viernes deja de decir «mañana»', function () {
        // La alerta tocaba el domingo 23 y diría «mañana (24 de agosto)». Al
        // adelantarse al viernes 21 tiene que decir «en tres días».
        expect(referenciaTemporalDe('2026-08-23', '2026-08-24')->frase())->toBe('mañana (24 de agosto)')
            ->and(referenciaTemporalDe('2026-08-21', '2026-08-24')->frase())->toBe('en tres días (24 de agosto)');
    });
});
