<?php

namespace App\Proveedor\Salida;

use App\Models\ConstanciaDeSalida;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use App\Presentacion\Formato;
use App\Proveedor\CreacionDeEmpresa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * La eliminación definitiva de los datos de una empresa que se fue
 * (documento 6, A.4, paso 3).
 *
 * **Es la única excepción a la regla 4 del proyecto —nunca borrado físico—**, y
 * está escrita así en el `CLAUDE.md`. La regla protege la historia de una
 * empresa mientras usa el sistema; esto ocurre cuando ya no lo usa, y lo exige
 * el principio de temporalidad de la Ley 1581 de 2012: terminado el servicio,
 * la finalidad se agotó y el proveedor no conserva nada.
 *
 * Por ser irreversible, solo procede si se cumplen los cuatro candados de
 * `impedimento()`: no es la demostración, el servicio está suspendido, su
 * información ya se entregó, y pasó el periodo de gracia desde la entrega.
 *
 * Borra físicamente, en una transacción —o todo o nada—, las tablas que
 * `TablasDeLaEmpresa` marca para eliminar, en el orden que piden sus llaves
 * foráneas. Antes de borrar, anonimiza el registro de alertas y suelta las
 * aceptaciones legales de sus usuarios, que son lo que se conserva (`PROD-15`).
 * Conserva además la empresa misma, sus suscripciones y pagos, las notas del
 * proveedor y las constancias de la salida, y deja la constancia de esta
 * eliminación.
 *
 * El filtro por `tenant_id` se escribe a mano por la misma razón que en
 * `ExportacionDeLaEmpresa`: query builder sobre tablas crudas, y en local la
 * base se conecta como superusuario. Una eliminación tiene que tocar una sola
 * empresa se conecte como se conecte.
 */
final class EliminacionDeLaEmpresa
{
    /**
     * Lo que queda en las columnas de texto obligatorias de una alerta anonimizada.
     */
    public const ANONIMIZADO = '(eliminado con la salida del cliente)';

    public function __construct(private readonly ContextoTenant $contexto) {}

    /**
     * Por qué no se puede eliminar todavía, o `null` si se puede.
     */
    public function impedimento(Tenant $empresa): ?string
    {
        if ($empresa->esFicticia()) {
            return 'Es el ambiente de demostración o una cuenta de prueba: no es un cliente y no tiene salida que tramitar.';
        }

        $eliminada = $empresa->getAttribute('datos_eliminados_en');

        if ($eliminada instanceof Carbon) {
            return 'Sus datos ya se eliminaron el '.Formato::fecha($eliminada).'.';
        }

        if (! $empresa->estaSuspendida()) {
            return 'El servicio de la empresa no está suspendido. Se elimina a quien ya se fue, nunca a un cliente activo.';
        }

        $exportada = $empresa->getAttribute('datos_exportados_en');

        if (! $exportada instanceof Carbon) {
            return 'Nunca se le entregó su información. Primero se exporta desde su ficha en el panel (A.4, paso 1).';
        }

        $dias = (int) config('panel.salida.dias_de_gracia', 30);
        $finDeLaGracia = $exportada->copy()->addDays($dias);

        if (Carbon::now()->lessThan($finDeLaGracia)) {
            return 'Su información se entregó el '.Formato::fecha($exportada)
                ." y el periodo de gracia de {$dias} días termina el ".Formato::fecha($finDeLaGracia).'.';
        }

        return null;
    }

    /**
     * @return array<string, int> Las filas eliminadas, por tabla.
     */
    public function eliminar(Tenant $empresa): array
    {
        $motivo = $this->impedimento($empresa);

        if ($motivo !== null) {
            throw new RuntimeException($motivo);
        }

        $rutaDelCertificado = $empresa->tieneCertificadoDeCamara()
            ? (string) $empresa->getAttribute('certificado_camara_ruta')
            : null;

        $conteo = $this->contexto->paraTenant($empresa, fn (): array => DB::transaction(function () use ($empresa): array {
            $id = (int) $empresa->getKey();

            // Las invitaciones no llevan `tenant_id`: cuelgan de los usuarios, y
            // tienen que irse antes que ellos.
            // Lo que se conserva se separa de lo que se va a borrar antes de
            // borrarlo: el registro de alertas apunta a vinculaciones y las
            // aceptaciones a usuarios, y los dos dejan de existir.
            $anonimizadas = $this->anonimizarAlertas($id);
            $this->soltarAceptaciones($id);

            $conteo = [
                'invitaciones' => DB::table('invitaciones')
                    ->whereIn('user_id', DB::table('users')->select('id')->where('tenant_id', $id))
                    ->delete(),
            ];

            foreach ($this->enOrdenDeBorrado(TablasDeLaEmpresa::aEliminar()) as $tabla) {
                $conteo[$tabla] = DB::table($tabla)->where('tenant_id', $id)->delete();
            }

            $empresa->forceFill([
                'datos_eliminados_en' => Carbon::now(),
                'certificado_camara_ruta' => null,
                'certificado_camara_nombre' => null,
            ])->save();

            $conteo['alertas_enviadas (anonimizadas)'] = $anonimizadas;

            ConstanciaDeSalida::query()->create([
                'tenant_id' => $id,
                'tipo' => ConstanciaDeSalida::ELIMINACION,
                'ocurrida_en' => Carbon::now(),
                'registrada_por' => auth()->user()?->getAttribute('name'),
                'detalle' => $conteo,
            ]);

            return $conteo;
        }));

        // El archivo, después de confirmar la transacción: si algo hubiera
        // fallado arriba, la empresa seguiría completa, también su certificado.
        if ($rutaDelCertificado !== null) {
            Storage::disk(CreacionDeEmpresa::discoDeCertificados())->delete($rutaDelCertificado);
        }

        return $conteo;
    }

    /**
     * Le borra al registro de alertas todo lo que identifica a una persona y
     * deja que la alerta salió, cuándo y con qué resultado.
     *
     * La clave se reemplaza porque es la idempotencia y la arma la regla con el
     * empleado dentro; la nueva sigue siendo única por empresa.
     */
    private function anonimizarAlertas(int $empresaId): int
    {
        return DB::table('alertas_enviadas')->where('tenant_id', $empresaId)->update([
            'vinculacion_id' => null,
            'clave' => DB::raw("'eliminada-' || id"),
            'asunto' => self::ANONIMIZADO,
            'cuerpo' => self::ANONIMIZADO,
            'destinatarios' => '[]',
            'motivo_del_fallo' => null,
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Suelta cada aceptación de su usuario, que se va a eliminar, sin perder
     * quién aceptó: el nombre y el correo viven en la aceptación desde que se
     * firmó. Las anteriores a esas columnas los toman aquí del usuario.
     */
    private function soltarAceptaciones(int $empresaId): void
    {
        DB::statement(
            'update aceptaciones_legales a set aceptado_por_nombre = u.name, aceptado_por_correo = u.email '
            .'from users u where u.id = a.user_id and a.tenant_id = ? and a.aceptado_por_nombre is null',
            [$empresaId],
        );

        DB::table('aceptaciones_legales')->where('tenant_id', $empresaId)->update(['user_id' => null]);
    }

    /**
     * Las tablas en un orden en que ninguna se borra antes que las que la
     * referencian, leído de las llaves foráneas de la base y no escrito a mano:
     * una tabla nueva entra en su sitio sin que nadie tenga que acordarse.
     *
     * @param  list<string>  $tablas
     * @return list<string>
     */
    private function enOrdenDeBorrado(array $tablas): array
    {
        /** @var list<object{hija: string, madre: string}> $llaves */
        $llaves = DB::select(<<<'SQL'
            select conrelid::regclass::text as hija, confrelid::regclass::text as madre
            from pg_constraint
            where contype = 'f' and connamespace = 'public'::regnamespace
            SQL);

        $orden = [];
        $pendientes = $tablas;

        while ($pendientes !== []) {
            $libres = array_values(array_filter($pendientes, function (string $tabla) use ($llaves, $pendientes): bool {
                foreach ($llaves as $llave) {
                    if ($llave->madre === $tabla && $llave->hija !== $tabla && in_array($llave->hija, $pendientes, true)) {
                        return false;
                    }
                }

                return true;
            }));

            if ($libres === []) {
                throw new RuntimeException('Las llaves foráneas entre las tablas de la empresa forman un ciclo: no hay un orden de borrado.');
            }

            $orden = [...$orden, ...$libres];
            $pendientes = array_values(array_diff($pendientes, $libres));
        }

        return $orden;
    }
}
