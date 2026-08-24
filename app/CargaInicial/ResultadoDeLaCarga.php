<?php

namespace App\CargaInicial;

use App\Models\RegistroDeCarga;

/**
 * Lo que quedó de una carga ejecutada: cuántas filas entraron y la constancia
 * que las respalda.
 *
 * Lleva dentro la simulación con la que se escribió —la de verdad, la que se
 * corrió al confirmar— porque es la única forma de que el mensaje de «se
 * cargaron 32 filas» y las 32 filas escritas sean literalmente el mismo número.
 */
final class ResultadoDeLaCarga
{
    public function __construct(
        public readonly ResultadoDeSimulacion $simulacion,
        public readonly RegistroDeCarga $registro,
    ) {}

    public function filasCargadas(): int
    {
        return $this->registro->filas;
    }

    public function plantilla(): Plantilla
    {
        return $this->simulacion->plantilla;
    }

    /**
     * El mensaje que se le enseña a quien acaba de cargar.
     *
     * Dice el número y dice de qué plantilla, porque una puesta en marcha son
     * hasta seis cargas seguidas y «se cargaron 32 filas» a secas no distingue
     * cuál de ellas acaba de entrar.
     */
    public function mensaje(): string
    {
        return 'Se cargaron '.$this->filasCargadas().' filas de «'.$this->plantilla()->nombre().'».';
    }
}
