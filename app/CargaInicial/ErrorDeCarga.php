<?php

namespace App\CargaInicial;

/**
 * Un error de una fila de una plantilla, con su número de fila y su motivo.
 *
 * **El número de fila es el del archivo**, el que se ve en la barra gris del
 * Excel: si el error está en la fila 7, dice 7. Es todo el valor del informe.
 * Un mensaje que diga «hay un error en el registro 5» obliga a contar filas a
 * mano, y quien lo hace se equivoca porque la primera fila es de títulos.
 *
 * La columna puede faltar. Los errores que no son de una celda —un documento
 * repetido, un histórico de alguien que no está en la planta— son de la fila
 * entera, y ahí inventar una columna sería peor que no ponerla.
 */
final class ErrorDeCarga
{
    public function __construct(
        public readonly int $fila,
        public readonly ?string $columna,
        public readonly string $motivo,
    ) {}

    /**
     * El error en una línea, como se lee en el informe.
     */
    public function comoTexto(): string
    {
        return $this->columna === null
            ? "Fila {$this->fila}: {$this->motivo}"
            : "Fila {$this->fila}, «{$this->columna}»: {$this->motivo}";
    }

    /**
     * @return array{fila: int, columna: string|null, motivo: string, texto: string}
     */
    public function paraPantalla(): array
    {
        return [
            'fila' => $this->fila,
            'columna' => $this->columna,
            'motivo' => $this->motivo,
            'texto' => $this->comoTexto(),
        ];
    }
}
