<?php

namespace Database\Seeders;

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Models\Ciudad;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\User;
use App\Multiempresa\ContextoTenant;
use App\Proveedor\PlantaDeMuestra;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Deja la máquina de quien desarrolla lista para entrar y trabajar.
 *
 * Nació de dos tropiezos reales de David montando el proyecto en una máquina
 * nueva: no había ninguna cuenta con la que entrar —el `Test User` del seeder
 * de Laravel tiene `tenant_id` nulo y rol `admin`, o sea un usuario de empresa
 * sin empresa, que no sirve para nada— y la única empresa que existía era la de
 * demostración, que es de **solo lectura** y por tanto inútil para probar nada
 * que se registre.
 *
 * Escribe tres cosas:
 *
 * 1. **La cuenta del proveedor**, para entrar al panel interno.
 * 2. **Una empresa normal y escribible**, con su superadministrador.
 * 3. **La misma planta que la demostración** (`PlantaDeMuestra`), para que la
 *    empresa de trabajo se vea igual de usada que la que ve un prospecto.
 *
 * **Fuera de local solo corre con una clave propia** (`PROD-31`). En local la
 * clave es `password`, que en un servidor abierto a internet sería una puerta
 * trasera. En un servidor de pruebas, como el primer despliegue en Railway,
 * corre solo si `NEXO_CLAVE_CUENTAS_DE_PRUEBA` trae una clave, y entonces las
 * dos cuentas usan esa clave y no `password`. El guardia está aquí dentro y no
 * en quien lo llama, para que no dependa de que alguien se acuerde.
 */
class DesarrolloSeeder extends Seeder
{
    public const CORREO_PROVEEDOR = 'owner@nexo.local';

    public const CORREO_EMPRESA = 'admin@empresademo.local';

    public const CLAVE = 'password';

    public const NOMBRE_EMPRESA = 'Empresa de Pruebas S.A.S.';

    public const NIT_EMPRESA = '901000002-2';

    public function __construct(private readonly ContextoTenant $contexto) {}

    public function run(): void
    {
        if (! app()->environment('local', 'testing') && self::claveDePrueba() === '') {
            throw new RuntimeException(
                'DesarrolloSeeder crea cuentas con contraseña conocida: fuera de local solo corre con '
                .'NEXO_CLAVE_CUENTAS_DE_PRUEBA definida.'
            );
        }

        $this->cuentaDelProveedor();

        $empresa = $this->empresaDeTrabajo();

        $this->poblar($empresa);
    }

    /**
     * La clave de las dos cuentas: la de `NEXO_CLAVE_CUENTAS_DE_PRUEBA` si se
     * definió, o `password` en local.
     */
    public static function clave(): string
    {
        $propia = self::claveDePrueba();

        return $propia !== '' ? $propia : self::CLAVE;
    }

    private static function claveDePrueba(): string
    {
        return trim((string) config('panel.clave_cuentas_de_prueba', ''));
    }

    /**
     * La cuenta con la que se entra al panel del proveedor.
     *
     * Sin segundo factor a propósito: el middleware desvía a activarlo en el
     * primer ingreso, que es el camino que se quiere probar. Dárselo hecho
     * escondería justamente la pantalla que hay que ver funcionando.
     */
    private function cuentaDelProveedor(): void
    {
        if (User::withTrashed()->where('email', self::CORREO_PROVEEDOR)->exists()) {
            return;
        }

        User::query()->create([
            'tenant_id' => null,
            'rol' => Rol::Owner,
            'name' => 'Proveedor (desarrollo)',
            'email' => self::CORREO_PROVEEDOR,
            'password' => Hash::make(self::clave()),
            'email_verified_at' => Carbon::now(),
        ]);
    }

    /**
     * La empresa escribible, con su superadministrador.
     *
     * **No lleva `es_ambiente_demo`**, y ahí está toda la diferencia con la de
     * demostración: sin esa marca no la toca `EntornoDeSoloLectura`, así que en
     * esta sí se pueden registrar novedades, corregirlas y borrarlas.
     *
     * Queda con `suspensiones_afectan_vacaciones` activo para que el saldo de
     * vacaciones ejercite la parte de la fórmula que la otra empresa no ejercita.
     */
    private function empresaDeTrabajo(): Tenant
    {
        $existente = Tenant::query()->where('nit', self::NIT_EMPRESA)->first();

        if ($existente instanceof Tenant) {
            return $existente;
        }

        return DB::transaction(function (): Tenant {
            $empresa = Tenant::query()->create([
                'nombre' => self::NOMBRE_EMPRESA,
                'nit' => self::NIT_EMPRESA,
                'telefono' => '6017000001',
                'ciudad_id' => $this->ciudad()->getKey(),
                'estado' => Tenant::ESTADO_ACTIVA,
                'sabado_laboral' => false,
                'suspensiones_afectan_vacaciones' => true,
                'perfil_sociodemografico_habilitado' => true,
                'es_ambiente_demo' => false,
            ]);

            $usuario = User::query()->create([
                'tenant_id' => $empresa->getKey(),
                'rol' => PlantaDeMuestra::rolDeLaCuentaPrincipal(),
                'name' => 'Administradora de pruebas',
                'email' => self::CORREO_EMPRESA,
                'password' => Hash::make(self::clave()),
                'email_verified_at' => Carbon::now(),
            ]);

            // El superadministrador los tiene todos por definición, pero la
            // fila explícita es la que leen las pantallas de configuración.
            //
            // Va dentro del contexto de la empresa porque `user_modulos` lleva
            // `tenant_id` y quien lo rellena es el trait `PerteneceATenant`,
            // leyendo `tenant_actual()`. Fuera del contexto no hay empresa
            // actual y la fila nace sin dueño.
            $this->contexto->paraTenant($empresa, function () use ($usuario): void {
                foreach (Modulo::cases() as $modulo) {
                    $usuario->modulos()->create(['modulo' => $modulo]);
                }
            });

            return $empresa;
        });
    }

    /**
     * Escribe la planta, salvo que la empresa ya la tenga.
     *
     * Volver a correr el seeder no duplica a nadie: es lo que se espera de
     * `db:seed`, que se ejecuta muchas veces en una máquina de desarrollo.
     *
     * **Con auditoría, al revés que la demostración.** Allá se silencia porque
     * la planta ficticia se borra y se reescribe cada noche y eso no es el
     * trabajo de nadie; `ObservadorDeAuditoria::sinAuditar()` de hecho solo se
     * deja usar dentro del ambiente de demostración, y es una protección que
     * conviene respetar en vez de rodear. Aquí la empresa es normal: que la
     * planta deje su rastro es lo correcto, y además le da contenido a la
     * pantalla de auditoría, que si no nacería vacía.
     */
    private function poblar(Tenant $empresa): void
    {
        $this->contexto->paraTenant($empresa, function (): void {
            if (Persona::query()->exists()) {
                return;
            }

            DB::transaction(function (): void {
                app(PlantaDeMuestra::class)->escribir();
            });
        });
    }

    private function ciudad(): Ciudad
    {
        $ciudad = Ciudad::query()->where('nombre', 'Bogotá D.C.')->first();

        return $ciudad instanceof Ciudad
            ? $ciudad
            : throw new RuntimeException('El catálogo de municipios todavía no tiene a Bogotá D.C.');
    }
}
