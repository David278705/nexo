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
 * El criterio lo decidió David el 2026-11-03 (`PROD-15`) sobre la
 * investigación del paso 14 del plan de salida al mercado, y lo valida el
 * abogado. Cinco listas:
 *
 * - **Del cliente**: los datos de sus empleados y de su operación. Se le
 *   entregan completos y después se eliminan.
 * - **Se entregan y se conservan**: la prueba de que el cliente aceptó el
 *   contrato. El proveedor la necesita para defenderse.
 * - **Se entregan y se anonimizan**: el registro de alertas. Queda que una
 *   alerta salió, cuándo y con qué resultado, sin nombres ni destinatarios.
 * - **Solo eliminar**: lo que no es del cliente o se recalcula de lo entregado.
 * - **Del proveedor**: los registros propios de la relación comercial —su
 *   contabilidad— y las constancias de la salida. Nunca se eliminan.
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
        'cargas_iniciales',
        'users',
        'user_modulos',
        'auditoria',
        'accesos',
        'visitas_al_entorno',
    ];

    /**
     * Se entregan y no se eliminan, con el motivo.
     *
     * @var array<string, string>
     */
    public const SE_ENTREGAN_Y_SE_CONSERVAN = [
        'aceptaciones_legales' => 'La prueba de que el cliente aceptó el contrato y el acuerdo de tratamiento, '
            .'con quién, cuándo y desde dónde. Conserva el nombre y el correo de quien aceptó.',
    ];

    /**
     * Se entregan y después se les borra lo que identifica a una persona, con el
     * motivo. Qué columnas se borran lo decide `EliminacionDeLaEmpresa`.
     *
     * @var array<string, string>
     */
    public const SE_ENTREGAN_Y_SE_ANONIMIZAN = [
        'alertas_enviadas' => 'Prueba de que el sistema avisó: tipo, fechas, estado y hora de envío, sin el '
            .'empleado, el contenido ni los destinatarios.',
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
        'constancias_de_salida' => 'Las constancias de la entrega y de la eliminación de la información.',
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
     * Todo lo que la exportación entrega.
     *
     * @return list<string>
     */
    public static function aEntregar(): array
    {
        return [
            ...self::DEL_CLIENTE,
            ...array_keys(self::SE_ENTREGAN_Y_SE_CONSERVAN),
            ...array_keys(self::SE_ENTREGAN_Y_SE_ANONIMIZAN),
        ];
    }

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
        return [
            ...self::aEliminar(),
            ...array_keys(self::SE_ENTREGAN_Y_SE_CONSERVAN),
            ...array_keys(self::SE_ENTREGAN_Y_SE_ANONIMIZAN),
            ...array_keys(self::DEL_PROVEEDOR),
        ];
    }
}
