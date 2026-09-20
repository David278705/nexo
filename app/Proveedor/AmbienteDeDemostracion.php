<?php

namespace App\Proveedor;

use App\Auditoria\ObservadorDeAuditoria;
use App\Autorizacion\Rol;
use App\Models\Ciudad;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Terminacion;
use App\Models\User;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * La empresa ficticia del ambiente de demostración (documento 4, capítulo 10,
 * F5-03): la que Angela enseña en la sesión guiada del proceso comercial
 * (12.1) y la que un acceso de prueba deja explorar solo.
 *
 * **Hay una sola, siempre.** El índice único parcial de la migración impide
 * que exista una segunda; `tenant()` la crea si todavía no existe y la
 * devuelve si ya existe, así que llamarlo dos veces nunca duplica nada.
 *
 * **`restaurar()` no es el `escribir()` de la carga inicial.** Ese espera
 * filas ya validadas de un archivo de Excel que aquí no hay ninguna razón para
 * inventar: esto escribe directamente con los modelos, igual que cualquier
 * prueba del sistema. Lo que sí comparte con la carga inicial es la regla 4
 * del proyecto —nunca borrado físico—: vaciar el ambiente es un `delete()`
 * lógico fila por fila, no un `truncate`, así que cada restauración dobla el
 * número de filas muertas en vez de perder el rastro de la anterior.
 *
 * **Los datos son fijos, no aleatorios.** Un `Faker` sin semilla dejaría el
 * ambiente distinto cada noche, y el «hecho cuando» de la tarea es justo lo
 * contrario: que luzca **igual** de poblado siempre. Los nombres, los cargos y
 * los documentos son los mismos en cada restauración; lo único relativo a hoy
 * son las fechas, para que el contrato a término fijo siga venciendo pronto y
 * la incapacidad siga siendo reciente sin importar qué día se mire la demo.
 */
final class AmbienteDeDemostracion
{
    public const NOMBRE = 'Empresa Demo Nexo S.A.S.';

    public const NIT = '900000001-1';

    public const CORREO_USUARIO_DEMO = 'demo@ambiente-nexo.local';

    public function __construct(private readonly ContextoTenant $contexto) {}

    /**
     * La empresa del ambiente, creándola la primera vez que se pide.
     */
    public function tenant(): Tenant
    {
        $tenant = Tenant::query()->where('es_ambiente_demo', true)->first();

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        return DB::transaction(function (): Tenant {
            $ciudad = $this->ciudadDeReferencia();

            $tenant = Tenant::query()->create([
                'nombre' => self::NOMBRE,
                'nit' => self::NIT,
                'telefono' => '6017000000',
                'ciudad_id' => $ciudad->getKey(),
                'estado' => Tenant::ESTADO_EN_PRUEBA,
                'sabado_laboral' => false,
                'suspensiones_afectan_vacaciones' => true,
                'perfil_sociodemografico_habilitado' => false,
                'es_ambiente_demo' => true,
            ]);

            $this->asegurarUsuarioDeDemostracion($tenant);

            return $tenant;
        });
    }

    /**
     * Vacía y vuelve a poblar el ambiente con el mismo conjunto de datos de
     * siempre. Es lo que corre el comando `demostracion:restaurar` cada noche
     * y el botón «Restaurar ahora» del panel.
     *
     * **No deja línea de auditoría.** Es la misma planta ficticia de siempre
     * borrándose y volviéndose a crear, no un cambio en los datos de un
     * cliente (REV-07); `ObservadorDeAuditoria::sinAuditar()` cubre también
     * el `demo_restaurado_en` de abajo, que si no quedaría como una edición
     * más de la empresa cada noche.
     */
    public function restaurar(): Tenant
    {
        $tenant = $this->tenant();

        $this->contexto->paraTenant($tenant, function () use ($tenant): void {
            DB::transaction(function () use ($tenant): void {
                ObservadorDeAuditoria::sinAuditar(function () use ($tenant): void {
                    $this->vaciar();
                    $this->poblar();
                    $tenant->forceFill(['demo_restaurado_en' => Carbon::now()])->save();
                });
            });
        });

        return $tenant->refresh();
    }

    /**
     * Lo que la pantalla del panel enseña del estado actual: cuándo quedó
     * poblada por última vez y cuántas personas trae.
     *
     * @return array{restauradoEn: Carbon|null, personas: int}
     */
    public function estado(): array
    {
        $tenant = $this->tenant();

        $personas = $this->contexto->paraTenant(
            $tenant,
            fn (): int => Persona::query()->count(),
        );

        return [
            'restauradoEn' => $tenant->getAttribute('demo_restaurado_en'),
            'personas' => $personas,
        ];
    }

    /**
     * Borra lógicamente todo lo que la restauración anterior dejó, en el
     * orden inverso al que se creó: primero lo que cuelga de la vinculación,
     * luego la vinculación y, al final, la persona.
     *
     * Se ejecuta ya dentro de `ContextoTenant::paraTenant()`, así que el
     * global scope de cada modelo hace el filtro por esta empresa solo.
     */
    private function vaciar(): void
    {
        foreach ([Terminacion::class, Movimiento::class, Licencia::class, Vacacion::class, Incapacidad::class] as $modelo) {
            foreach ($modelo::query()->get() as $registro) {
                $registro->delete();
            }
        }

        foreach (Vinculacion::query()->get() as $vinculacion) {
            foreach ($vinculacion->periodos()->get() as $periodo) {
                $periodo->delete();
            }

            $vinculacion->delete();
        }

        foreach (Persona::query()->get() as $persona) {
            $persona->delete();
        }
    }

    /**
     * Escribe la planta dentro de la empresa, que ya está en contexto.
     *
     * **La planta dejó de vivir aquí.** Está en `PlantaDeMuestra`, que la
     * comparte con el seeder de desarrollo: la empresa que explora un prospecto
     * y la que usa quien construye el sistema tienen que tener los mismos
     * datos, porque si no lo que se prueba deja de ser lo que se enseña.
     */
    private function poblar(): void
    {
        app(PlantaDeMuestra::class)->escribir();
    }

    private function asegurarUsuarioDeDemostracion(Tenant $tenant): User
    {
        $usuario = User::query()->where('email', self::CORREO_USUARIO_DEMO)->first();

        if ($usuario instanceof User) {
            return $usuario;
        }

        return User::query()->create([
            'tenant_id' => $tenant->getKey(),
            'rol' => Rol::Superadministrador,
            'name' => 'Cuenta de demostración',
            'email' => self::CORREO_USUARIO_DEMO,
            'password' => bin2hex(random_bytes(32)),
            'email_verified_at' => Carbon::now(),
        ]);
    }

    private function ciudadDeReferencia(): Ciudad
    {
        $ciudad = Ciudad::query()->where('nombre', 'Bogotá D.C.')->first();

        return $ciudad instanceof Ciudad
            ? $ciudad
            : throw new RuntimeException('El catálogo de municipios todavía no tiene a Bogotá D.C.');
    }

    /**
     * El usuario compartido con el que entra quien tenga un acceso de prueba
     * vigente (documento 4, capítulo 10). Es uno solo: varios prospectos
     * explorando a la vez comparten la misma sesión de datos, que es
     * exactamente lo que se espera de un ambiente de demostración.
     */
    public function usuarioDeDemostracion(): User
    {
        $usuario = User::query()->where('email', self::CORREO_USUARIO_DEMO)->first();

        return $usuario ?? $this->asegurarUsuarioDeDemostracion($this->tenant());
    }
}
