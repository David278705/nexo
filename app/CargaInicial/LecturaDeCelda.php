<?php

namespace App\CargaInicial;

use DateTimeImmutable;

/**
 * Convierte una celda de Excel en el valor que guarda el sistema.
 *
 * Es el único sitio de la carga donde se interpreta lo que alguien escribió, y
 * está separado de la validación a propósito. Son dos preguntas distintas:
 *
 * - **¿Esto se puede leer?** Es de aquí. «14/07/2025» es una fecha, «catorce de
 *   julio» no lo es; «Sura EPS» está en el catálogo, «Sura» no.
 * - **¿Esto es admisible?** Es de las reglas de cada plantilla. Que la fecha de
 *   ingreso no sea futura o que el salario no sea negativo son condiciones sobre
 *   un valor que ya se leyó.
 *
 * Una celda vacía devuelve nulo y no es un error aquí: si esa columna era
 * obligatoria lo dirá su regla, con el mismo mensaje que daría el formulario.
 *
 * ## Las fechas
 *
 * Se admiten las dos formas que las plantillas anuncian: `DD/MM/AAAA` escrito a
 * mano y la fecha real de Excel, que `LectorDeArchivo` ya tradujo a `AAAA-MM-DD`
 * antes de llegar aquí. Las dos se distinguen sin ambigüedad, así que no hay
 * riesgo de leer el 3 de febrero como el 2 de marzo.
 *
 * ## Los números
 *
 * Si Excel ya guardó la celda como número, ese número se toma tal cual. Si lo
 * que hay es texto, el único separador decimal admitido es la **coma**, que es
 * la de Colombia y la que la plantilla pone de ejemplo para el saldo de
 * vacaciones («12,75»).
 *
 * El punto en un texto se rechaza, y no por purismo: «3.200» escrito a mano en
 * Colombia son tres mil doscientos, y leerlo como tres con dos décimas
 * convertiría un salario en calderilla sin que nadie se enterara. Un número
 * ambiguo se devuelve al proveedor para que lo corrija; no se interpreta.
 *
 * El dinero sale de aquí como **texto** de dos decimales. Es la convención del
 * proyecto —dinero nunca en coma flotante— y además es la forma en la que el
 * formulario del módulo recibe un salario, así que las mismas reglas de
 * validación juzgan lo mismo.
 */
final class LecturaDeCelda
{
    public function __construct(private readonly CatalogosDeCarga $catalogos) {}

    /**
     * @throws CeldaIlegible
     */
    public function valor(Columna $columna, mixed $bruto): mixed
    {
        $texto = TextoDeCelda::limpio($bruto);

        if ($texto === null) {
            return null;
        }

        return match ($columna->tipo) {
            TipoDeCelda::Texto => $texto,
            TipoDeCelda::Fecha => $this->fecha($texto),
            TipoDeCelda::Entero => $this->entero($bruto, $texto),
            TipoDeCelda::Decimal => $this->decimal($bruto, $texto),
            TipoDeCelda::Dinero => number_format($this->decimal($bruto, $texto), 2, '.', ''),
            TipoDeCelda::SiNo => $this->siNo($texto),
            TipoDeCelda::Lista => $this->deLaLista($columna, $texto),
            TipoDeCelda::Catalogo => $this->delCatalogo($columna, $texto),
        };
    }

    private function fecha(string $texto): string
    {
        foreach (['d/m/Y', 'Y-m-d'] as $formato) {
            // La admiración delante del formato pone a cero todo lo que el
            // texto no diga. Sin ella, «14/07/2025» heredaría la hora del
            // reloj del servidor y una fecha laboral no tiene hora.
            $fecha = DateTimeImmutable::createFromFormat('!'.$formato, $texto);
            $problemas = DateTimeImmutable::getLastErrors();

            if ($fecha instanceof DateTimeImmutable && $problemas === false) {
                return $fecha->format('Y-m-d');
            }
        }

        throw new CeldaIlegible("«{$texto}» no es una fecha. Escríbala como DD/MM/AAAA.");
    }

    private function entero(mixed $bruto, string $texto): int
    {
        if (is_int($bruto) || (is_float($bruto) && $bruto === floor($bruto))) {
            return (int) $bruto;
        }

        if (preg_match('/^-?\d+$/', $texto) !== 1) {
            throw new CeldaIlegible("«{$texto}» no es un número entero. Escriba solo números, sin puntos ni comas.");
        }

        return (int) $texto;
    }

    private function decimal(mixed $bruto, string $texto): float
    {
        if (is_int($bruto) || is_float($bruto)) {
            return (float) $bruto;
        }

        if (preg_match('/^-?\d+(?:,\d+)?$/', $texto) !== 1) {
            throw new CeldaIlegible("«{$texto}» no es un número. Escriba solo números, sin puntos de miles y con coma para los decimales.");
        }

        return (float) str_replace(',', '.', $texto);
    }

    /**
     * El «Sí o No» de la columna de prórroga de la plantilla de incapacidades,
     * en el mismo «si» y «no» que recibe el formulario del módulo.
     */
    private function siNo(string $texto): string
    {
        return match (TextoDeCelda::comparable($texto)) {
            'si' => 'si',
            'no' => 'no',
            default => throw new CeldaIlegible("«{$texto}» no se entiende. Escriba Sí o No."),
        };
    }

    private function deLaLista(Columna $columna, string $texto): string
    {
        $comparable = TextoDeCelda::comparable($texto);

        foreach ($columna->opciones as $opcion) {
            if (TextoDeCelda::comparable($opcion['texto']) === $comparable) {
                return $opcion['valor'];
            }
        }

        foreach ($columna->sinonimos as $sinonimo => $valor) {
            if (TextoDeCelda::comparable($sinonimo) === $comparable) {
                return $valor;
            }
        }

        $admitidos = implode(' · ', $columna->textosAdmitidos());

        throw new CeldaIlegible("«{$texto}» no está en la lista. Los valores admitidos son: {$admitidos}.");
    }

    private function delCatalogo(Columna $columna, string $texto): int
    {
        $catalogo = $columna->catalogo;

        if ($catalogo === null) {
            throw new CeldaIlegible("«{$texto}» no se pudo comprobar: la columna «{$columna->titulo}» no dice contra qué catálogo va.");
        }

        return $this->catalogos->entidad($catalogo, $texto)
            ?? throw new CeldaIlegible("«{$texto}» no está en el catálogo de «{$columna->titulo}» del sistema. Escríbalo tal como aparece en la lista.");
    }
}
