<?php

namespace App\Proveedor\Salida;

/**
 * Qué pasa con cada tabla de una empresa cuando se va (documento 6, A.4).
 *
 * **Un solo sitio para las tres listas**, porque la exportación y la
 * eliminación tienen que estar de acuerdo: lo que se elimina sin haberse
 * entregado se pierde, y lo que se entrega sin eliminarse se queda donde no
 * debe. `TablasDeLaEmpresaTest` comprueba que toda tabla con `tenant_id` esté
 * en exactamente una lista, así que una tabla nueva no puede quedar fuera sin
 * que la batería se ponga en rojo.
 *
 * El criterio, que es una lectura de A.4 y de la Ley 1581 de 2012 y conviene
 * que lo confirme el abogado (está en `ESTADO.md`):
 *
 * - **Del cliente**: los datos de sus empleados y de su operación. Se le
 *   entregan completos y después se eliminan: «el proveedor no conserva nada».
 * - **Solo eliminar**: lo que no es del cliente o se recalcula de lo entregado.
 * - **Del proveedor**: los registros propios de la relación comercial, que el
 *   proveedor sí tiene que conservar (su contabilidad), y que no son datos
 *   personales de los empleados del cliente.
 */
final class TablasDeLaEmpresa
{
    /**
     * Se entregan y se eliminan.
     *
     * @var list<string>
     */
    public const DEL_CLIENTE = [
        'personas',
        'vinculaciones',
        'periodos_contractuales',
        'perfil_sociodemografico',
        'incapacidades',
        'vacaciones',
        'licencias',
        'movimientos',
        'terminaciones',
        'alertas_enviadas',
        'cargas_iniciales',
        'users',
        'user_modulos',
        'aceptaciones_legales',
        'auditoria',
        'accesos',
        'visitas_al_entorno',
    ];

    /**
     * Se eliminan sin entregarse, con el motivo.
     *
     * @var array<string, string>
     */
    public const SOLO_ELIMINAR = [
        'saldos_en_cache' => 'Es un cálculo guardado: sale de nuevo de los datos entregados.',
        'accesos_de_prueba' => 'Solo existen en el ambiente de demostración, nunca en un cliente.',
    ];

    /**
     * Ni se entregan ni se eliminan: son del proveedor.
     *
     * @var array<string, string>
     */
    public const DEL_PROVEEDOR = [
        'suscripciones' => 'La relación comercial y su facturación, con sus pagos: contabilidad del proveedor.',
        'notas_cliente' => 'Notas internas del proveedor sobre la cuenta.',
    ];

    /**
     * Columnas que nunca salen en una exportación, aunque la tabla sí salga.
     *
     * @var array<string, list<string>>
     */
    public const COLUMNAS_SECRETAS = [
        'users' => ['password', 'remember_token', 'segundo_factor_secreto'],
    ];

    /**
     * Todo lo que la eliminación borra.
     *
     * @return list<string>
     */
    public static function aEliminar(): array
    {
        return [...self::DEL_CLIENTE, ...array_keys(self::SOLO_ELIMINAR)];
    }

    /**
     * @return list<string>
     */
    public static function clasificadas(): array
    {
        return [...self::aEliminar(), ...array_keys(self::DEL_PROVEEDOR)];
    }
}
