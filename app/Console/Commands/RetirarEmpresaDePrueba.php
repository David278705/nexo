<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DesarrolloSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retira la empresa de pruebas de `DesarrolloSeeder` de un servidor (`PROD-32`).
 *
 * En Railway la siembra crea dos cuentas: la del proveedor y una empresa de
 * pruebas con su superadministrador. Cuando quien prueba quiere crear su propia
 * empresa desde el panel, la de pruebas sobra. Este comando le hace **borrado
 * lógico** a esa empresa y a sus usuarios —con auditoría, por el observador— y
 * deja intacta la cuenta del proveedor.
 *
 * Solo toca la empresa con el NIT de la siembra: nunca a un cliente. Una
 * empresa con borrado lógico no recibe alertas ni aparece en el panel, y la
 * siembra no la vuelve a crear. Correrlo otra vez no hace nada.
 *
 * `docker/arrancar.sh` lo corre si `NEXO_RETIRAR_EMPRESA_DE_PRUEBA=si`, porque
 * Railway no da consola dentro del contenedor.
 */
class RetirarEmpresaDePrueba extends Command
{
    protected $signature = 'cuentas-de-prueba:retirar-empresa';

    protected $description = 'Borrado lógico de la empresa de pruebas de la siembra y de sus usuarios';

    public function handle(): int
    {
        $empresa = Tenant::query()->where('nit', DesarrolloSeeder::NIT_EMPRESA)->first();

        if (! $empresa instanceof Tenant) {
            $this->line('No hay empresa de pruebas activa: no se retira nada.');

            return self::SUCCESS;
        }

        $usuarios = DB::transaction(function () use ($empresa): int {
            $usuarios = 0;

            foreach ($empresa->usuarios()->get() as $usuario) {
                /** @var User $usuario */
                $usuario->delete();
                $usuarios++;
            }

            $empresa->delete();

            return $usuarios;
        });

        $this->info("Empresa de pruebas retirada ({$empresa->getAttribute('nombre')}) con {$usuarios} usuario(s). La cuenta del proveedor sigue igual.");

        return self::SUCCESS;
    }
}
