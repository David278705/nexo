<?php

namespace App\Contratacion;

/**
 * De dónde salió una vinculación (`PROD-33`, decidido por Angela el 2026-11-11).
 *
 * Importa para la regla de prórrogas del término fijo: de un contrato que entró
 * por la carga inicial no hay cómo saber si sus prórrogas anteriores a Nexo
 * constaron por escrito, así que se tratan como automáticas y el límite de
 * prórrogas cortas es siempre de tres.
 */
enum OrigenDeVinculacion: string
{
    case Registro = 'registro';
    case CargaInicial = 'carga_inicial';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Registro => 'Registrado en Nexo',
            self::CargaInicial => 'Carga inicial',
        };
    }
}
