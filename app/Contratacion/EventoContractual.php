<?php

namespace App\Contratacion;

/**
 * Qué hecho abrió cada tramo del contrato (documento 2, 3.3).
 *
 * Son cuatro y cada una es un hecho jurídico distinto, no una etiqueta:
 *
 * - **inicial**: el contrato original. Es siempre el tramo con `orden = 1` y
 *   existe en toda vinculación, también en las indefinidas.
 * - **prorroga_pactada**: las partes acordaron por escrito seguir, y la
 *   duración la eligieron ellas.
 * - **prorroga_automatica**: nadie avisó y el contrato se entiende prorrogado
 *   por el término inicialmente pactado o por el de su última prórroga
 *   (artículo 46 del CST reformado por la Ley 2466 de 2025).
 * - **cambio_a_indefinido**: la modalidad cambió, por voluntad de la empresa o
 *   porque el vínculo alcanzó el tope legal de cuatro años.
 *
 * ## El tipo de prórroga decide cuánto dura, nunca cuántas caben
 *
 * Es la corrección de C.1 que hizo Angela en agosto de 2026, y conviene tenerla
 * presente porque la primera lectura del documento llevó a construirlo al revés.
 * Un contrato tiene **una sola cadena de prórrogas**, numeradas 1ª, 2ª, 3ª, y el
 * cupo de tres prórrogas cortas es uno solo para todas. Lo único que cambia con
 * el tipo es el plazo del tramo nuevo: la automática se renueva por el mismo
 * término anterior y la pactada por el que acuerden las partes. Por eso el cupo
 * no vive aquí sino en `HistorialDeContrato`, que es quien cuenta la cadena.
 *
 * Distinguirlas es la decisión D.1 de `docs/06-decisiones-pendientes.md`,
 * resuelta como propone ese documento: la acción «Renovar contrato» pregunta de
 * cuál de las dos se trata, y el documento 3, sección 5.4, la lista entre los
 * campos del modal. Sigue sirviendo para el historial del contrato y para la
 * frase informativa del modal, que son las dos cosas que D.1 le pide.
 */
enum EventoContractual: string
{
    case Inicial = 'inicial';

    case ProrrogaPactada = 'prorroga_pactada';

    case ProrrogaAutomatica = 'prorroga_automatica';

    case CambioAIndefinido = 'cambio_a_indefinido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Inicial => 'Periodo inicial',
            self::ProrrogaPactada => 'Prórroga pactada',
            self::ProrrogaAutomatica => 'Prórroga automática',
            self::CambioAIndefinido => 'Cambio a término indefinido',
        };
    }

    /**
     * Si el tramo cuenta como prórroga.
     *
     * El cambio a indefinido **no** es una prórroga: no extiende el plazo de un
     * contrato a término fijo, lo convierte en otra cosa. Sumarlo al conteo
     * adelantaría el límite de prórrogas cortas de la regla del artículo 46.
     */
    public function esProrroga(): bool
    {
        return $this === self::ProrrogaPactada || $this === self::ProrrogaAutomatica;
    }

    public function esInicial(): bool
    {
        return $this === self::Inicial;
    }

    /**
     * Las dos prórrogas, que son las opciones de la acción «Renovar contrato»
     * (documento 3, sección 5.4, y decisión D.1 del documento 6).
     *
     * El periodo inicial no se elige —lo abre la vinculación al nacer— y el
     * cambio a indefinido es otra acción con su propio botón.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public static function prorrogasParaElegir(): array
    {
        return [
            ['valor' => self::ProrrogaPactada->value, 'texto' => 'Pactada por escrito'],
            ['valor' => self::ProrrogaAutomatica->value, 'texto' => 'Automática por silencio'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function valoresDeProrroga(): array
    {
        return [self::ProrrogaPactada->value, self::ProrrogaAutomatica->value];
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function paraElegir(): array
    {
        return array_map(
            fn (self $evento): array => ['valor' => $evento->value, 'texto' => $evento->etiqueta()],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $evento): string => $evento->value, self::cases());
    }
}
