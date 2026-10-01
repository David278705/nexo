<?php

namespace App\Proveedor\Salida;

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
 * foráneas. Conserva la empresa misma, sus suscripciones y pagos, y las notas
 * del proveedor: son registros comerciales del proveedor, no datos personales.
 *
 * El filtro por `tenant_id` se escribe a mano por la misma razón que en
 * `ExportacionDeLaEmpresa`: query builder sobre tablas crudas, y en local la
 * base se conecta como superusuario. Una eliminación tiene que tocar una sola
 * empresa se conecte como se conecte.
 */
final class EliminacionDeLaEmpresa
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    /**
     * Por qué no se puede eliminar todavía, o `null` si se puede.
     */
    public function impedimento(Tenant $empresa): ?string
    {
        if ($empresa->getAttribute('es_ambiente_demo') === true) {
            return 'Es el ambiente de demostración: se restaura, no se elimina.';
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
