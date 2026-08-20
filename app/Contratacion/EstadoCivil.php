<?php

namespace App\Contratacion;

/**
 * El estado civil del perfil sociodemográfico (documento 1, 5.2).
 *
 * Las seis opciones son las de la columna «Estado Civil» de
 * `plantillas/Listas_Desplegables_Formulario_Empleados.xlsx`, que es la
 * respuesta de Angela a la decisión D.5. No se inventó ninguna.
 *
 * Como todo el bloque, el campo es opcional aunque la empresa lo tenga activo.
 */
enum EstadoCivil: string
{
    use OpcionesDeLista;

    case Soltero = 'soltero';

    case Casado = 'casado';

    case UnionLibre = 'union_libre';

    case Separado = 'separado';

    case Divorciado = 'divorciado';

    case Viudo = 'viudo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Soltero => 'Soltero(a)',
            self::Casado => 'Casado(a)',
            self::UnionLibre => 'Unión libre',
            self::Separado => 'Separado(a)',
            self::Divorciado => 'Divorciado(a)',
            self::Viudo => 'Viudo(a)',
        };
    }
}
