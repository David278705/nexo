<?php

namespace App\Auditoria;

use App\Autorizacion\Modulo;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\Movimiento;
use App\Models\PerfilSociodemografico;
use App\Models\PeriodoContractual;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Terminacion;
use App\Models\User;
use App\Models\UserModulo;
use App\Models\Vacacion;
use App\Models\Vinculacion;

/**
 * En qué sección del menú ocurrió una línea de auditoría (documento 1, 8.2:
 * «Módulo: dónde ocurrió»; el prototipo lo confirma con «Empleados»,
 * «Vacaciones», «Incapacidades» como opciones del filtro, no con el nombre
 * técnico de la tabla).
 *
 * `Persona` y `Vinculacion` no tienen cada una su propio módulo del menú, así
 * que se reparten entre los dos que sí existen: los datos de la persona son
 * Empleados y el contrato —la vinculación y su historial de periodos— es
 * Contratos. `User`, `UserModulo` y la propia empresa cuelgan de
 * Configuración, que es donde vive «Usuarios y permisos» (documento 3, 5.7).
 *
 * Lo que no aparece aquí —la carga inicial, los catálogos globales— no tiene
 * módulo del menú del cliente y se enseña con el nombre corto de su tabla.
 */
final class ModuloDelRegistro
{
    /**
     * @var array<string, list<class-string>>
     */
    private const MODELOS = [
        'empleados' => [Persona::class, PerfilSociodemografico::class],
        'contratos' => [Vinculacion::class, PeriodoContractual::class],
        'incapacidades' => [Incapacidad::class],
        'vacaciones' => [Vacacion::class],
        'licencias' => [Licencia::class],
        'movimientos' => [Movimiento::class],
        'terminaciones' => [Terminacion::class],
        'configuracion' => [User::class, UserModulo::class, Tenant::class],
    ];

    /**
     * La clave del filtro para este modelo, o nula si no cuelga de ningún
     * módulo del menú.
     */
    public static function clave(string $modelo): ?string
    {
        foreach (self::MODELOS as $clave => $modelos) {
            if (in_array($modelo, $modelos, true)) {
                return $clave;
            }
        }

        return null;
    }

    /**
     * El texto que se enseña en la columna «Módulo» y en la etiqueta del
     * filtro aplicado.
     */
    public static function etiqueta(string $modelo): string
    {
        $clave = self::clave($modelo);

        return $clave !== null ? Modulo::from($clave)->etiqueta() : class_basename($modelo);
    }

    /**
     * Las opciones del filtro, en el orden del menú.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (string $clave): array => ['valor' => $clave, 'texto' => Modulo::from($clave)->etiqueta()],
            array_keys(self::MODELOS),
        );
    }

    /**
     * Los modelos que caen bajo esta clave, para el `whereIn` del filtro.
     *
     * @return list<class-string>
     */
    public static function modelosDe(string $clave): array
    {
        return self::MODELOS[$clave] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function claves(): array
    {
        return array_keys(self::MODELOS);
    }
}
