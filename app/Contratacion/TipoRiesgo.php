<?php

namespace App\Contratacion;

/**
 * El nivel de riesgo con el que se afilia a la persona a la ARL
 * (documento 1, 5.1: «Niveles I a V»).
 *
 * Las cinco etiquetas están copiadas de la columna «Tipo de Riesgo ARL» de
 * `plantillas/Listas_Desplegables_Formulario_Empleados.xlsx`, la misma hoja que
 * resolvió la decisión D.5. Van con su descripción y no solo con el número
 * romano porque así vienen del archivo y porque «Riesgo IV» a secas no le dice
 * nada a quien llena el formulario.
 *
 * No es un catálogo de base de datos como las EPS o las ARL: los cinco niveles
 * los fija la ley, no cambian con el mercado y nadie los administra desde el
 * panel del proveedor.
 */
enum TipoRiesgo: string
{
    case Uno = 'riesgo_i';

    case Dos = 'riesgo_ii';

    case Tres = 'riesgo_iii';

    case Cuatro = 'riesgo_iv';

    case Cinco = 'riesgo_v';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Uno => 'Riesgo I – Mínimo',
            self::Dos => 'Riesgo II – Bajo',
            self::Tres => 'Riesgo III – Medio',
            self::Cuatro => 'Riesgo IV – Alto',
            self::Cinco => 'Riesgo V – Máximo',
        };
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function paraElegir(): array
    {
        return array_map(
            fn (self $riesgo): array => ['valor' => $riesgo->value, 'texto' => $riesgo->etiqueta()],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $riesgo): string => $riesgo->value, self::cases());
    }
}
