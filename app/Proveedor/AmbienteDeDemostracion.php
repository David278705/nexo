<?php

namespace App\Proveedor;

use App\Auditoria\ObservadorDeAuditoria;
use App\Autorizacion\Rol;
use App\Contratacion\Genero;
use App\Contratacion\TipoContrato;
use App\Contratacion\TipoDocumento;
use App\Contratacion\TipoRiesgo;
use App\Incapacidades\OrigenIncapacidad;
use App\Licencias\TipoDeLicencia;
use App\Models\Arl;
use App\Models\Banco;
use App\Models\CajaCompensacion;
use App\Models\Ciudad;
use App\Models\Eps;
use App\Models\FondoPension;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\Movimiento;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Terminacion;
use App\Models\User;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Movimientos\TipoDeMovimiento;
use App\Multiempresa\ContextoTenant;
use App\Terminaciones\TipoDeSalida;
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

    private function poblar(): void
    {
        $ciudad = $this->ciudadDeReferencia();
        $eps = $this->primeraEntidad(Eps::class);
        $fondo = $this->primeraEntidad(FondoPension::class);
        $arl = $this->primeraEntidad(Arl::class);
        $caja = $this->primeraEntidad(CajaCompensacion::class);
        $banco = $this->primeraEntidad(Banco::class);

        $hoy = Carbon::today();

        foreach ($this->planta($hoy) as $especificacion) {
            $persona = Persona::query()->create([
                'tipo_documento' => TipoDocumento::CedulaCiudadania,
                'numero_documento' => $especificacion['documento'],
                'fecha_expedicion_documento' => $especificacion['fechaNacimiento']->clone()->addYears(18),
                'primer_nombre' => $especificacion['primerNombre'],
                'segundo_nombre' => null,
                'primer_apellido' => $especificacion['primerApellido'],
                'segundo_apellido' => $especificacion['segundoApellido'],
                'fecha_nacimiento' => $especificacion['fechaNacimiento'],
                'genero' => $especificacion['genero'],
                'pais_nacimiento' => Pais::COLOMBIA,
                'ciudad_nacimiento_id' => $ciudad->getKey(),
                'pais_expedicion' => Pais::COLOMBIA,
                'ciudad_expedicion_id' => $ciudad->getKey(),
                'direccion' => 'Calle de muestra # 1-23',
                'ciudad_residencia_id' => $ciudad->getKey(),
            ]);

            $vinculacion = $persona->vinculaciones()->create([
                'cargo' => $especificacion['cargo'],
                'tipo_contrato' => $especificacion['tipoContrato'],
                'fecha_ingreso' => $especificacion['fechaIngreso'],
                'fecha_fin' => $especificacion['fechaFin'] ?? null,
                'salario' => $especificacion['salario'],
                'bonificacion' => 0,
                'eps_id' => $eps->getKey(),
                'fondo_pension_id' => $fondo->getKey(),
                'arl_id' => $arl->getKey(),
                'tipo_riesgo' => TipoRiesgo::Uno,
                'caja_compensacion_id' => $caja->getKey(),
                'banco_id' => $banco->getKey(),
            ]);

            ($especificacion['novedad'] ?? null)?->__invoke($vinculacion, $eps, $hoy);
        }
    }

    /**
     * La planta fija de la demostración: ocho personas, cada una mostrando
     * una situación distinta de las que el sistema resuelve. Las fechas son
     * relativas a hoy para que la demo siga teniendo sentido cualquier día que
     * se restaure —el contrato a término fijo sigue venciendo pronto, la
     * incapacidad sigue siendo reciente— sin dejar de ser la misma planta.
     *
     * @return list<array<string, mixed>>
     */
    private function planta(Carbon $hoy): array
    {
        return [
            [
                'documento' => '1000000001',
                'primerNombre' => 'Juliana',
                'primerApellido' => 'Restrepo',
                'segundoApellido' => 'Vélez',
                'genero' => Genero::Femenino,
                'fechaNacimiento' => Carbon::parse('1994-03-12'),
                'cargo' => 'Analista de Nómina',
                'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subDays(20),
                'salario' => 2_600_000,
                // En periodo de prueba: ingresó hace apenas 20 días.
                'novedad' => null,
            ],
            [
                'documento' => '1000000002',
                'primerNombre' => 'Andrés',
                'primerApellido' => 'Gómez',
                'segundoApellido' => 'Naranjo',
                'genero' => Genero::Masculino,
                'fechaNacimiento' => Carbon::parse('1990-07-08'),
                'cargo' => 'Desarrollador Backend',
                'tipoContrato' => TipoContrato::Fijo,
                'fechaIngreso' => $hoy->clone()->subMonths(11),
                'fechaFin' => $hoy->clone()->addDays(25),
                'salario' => 4_800_000,
                // A término fijo y por vencer pronto: dispara las alertas de
                // preaviso de renovación.
                'novedad' => null,
            ],
            [
                'documento' => '1000000003',
                'primerNombre' => 'Camila',
                'primerApellido' => 'Ortiz',
                'segundoApellido' => 'Salazar',
                'genero' => Genero::Femenino,
                'fechaNacimiento' => Carbon::parse('1988-11-30'),
                'cargo' => 'Coordinadora de Talento Humano',
                'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subYears(3),
                'salario' => 5_200_000,
                'novedad' => function (Vinculacion $vinculacion) use ($hoy): void {
                    Vacacion::query()->create([
                        'vinculacion_id' => $vinculacion->getKey(),
                        'fecha_inicio' => $hoy->clone()->subMonths(2)->startOfMonth(),
                        'fecha_fin' => $hoy->clone()->subMonths(2)->startOfMonth()->addDays(9),
                    ]);
                },
            ],
            [
                'documento' => '1000000004',
                'primerNombre' => 'Santiago',
                'primerApellido' => 'Herrera',
                'segundoApellido' => 'Buitrago',
                'genero' => Genero::Masculino,
                'fechaNacimiento' => Carbon::parse('1996-05-19'),
                'cargo' => 'Auxiliar Contable',
                'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subMonths(8),
                'salario' => 2_100_000,
                'novedad' => function (Vinculacion $vinculacion, Eps $eps) use ($hoy): void {
                    Incapacidad::query()->create([
                        'vinculacion_id' => $vinculacion->getKey(),
                        'fecha_inicio' => $hoy->clone()->subDays(7),
                        'fecha_fin' => $hoy->clone()->subDays(4),
                        'codigo_diagnostico' => 'J00',
                        'es_prorroga' => false,
                        'eps_id' => $eps->getKey(),
                        'origen' => OrigenIncapacidad::Comun,
                    ]);
                },
            ],
            [
                'documento' => '1000000005',
                'primerNombre' => 'Valentina',
                'primerApellido' => 'Cárdenas',
                'segundoApellido' => 'Mejía',
                'genero' => Genero::Femenino,
                'fechaNacimiento' => Carbon::parse('1993-01-22'),
                'cargo' => 'Analista de Mercadeo',
                'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subYear(),
                'salario' => 3_000_000,
                'novedad' => function (Vinculacion $vinculacion) use ($hoy): void {
                    Licencia::query()->create([
                        'vinculacion_id' => $vinculacion->getKey(),
                        'tipo' => TipoDeLicencia::LicenciaNoRemunerada,
                        'fecha_inicio' => $hoy->clone()->subDays(2),
                        'fecha_fin' => $hoy->clone()->addDays(3),
                    ]);
                },
            ],
            [
                'documento' => '1000000006',
                'primerNombre' => 'Diego',
                'primerApellido' => 'Martínez',
                'segundoApellido' => 'Rincón',
                'genero' => Genero::Masculino,
                'fechaNacimiento' => Carbon::parse('1985-09-02'),
                'cargo' => 'Gerente Comercial',
                'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subYears(2),
                'salario' => 7_500_000,
                'novedad' => function (Vinculacion $vinculacion) use ($hoy): void {
                    $vinculacion->registrarMovimiento(
                        TipoDeMovimiento::AumentoSalarial,
                        null,
                        '8200000',
                        $hoy->clone()->subMonth(),
                    );
                },
            ],
            [
                'documento' => '1000000007',
                'primerNombre' => 'Laura',
                'primerApellido' => 'Jiménez',
                'segundoApellido' => 'Pardo',
                'genero' => Genero::Femenino,
                'fechaNacimiento' => Carbon::parse('1998-04-14'),
                'cargo' => 'Auxiliar de Servicios Generales',
                'tipoContrato' => TipoContrato::ObraLabor,
                'fechaIngreso' => $hoy->clone()->subMonths(4),
                'salario' => 1_600_000,
                'novedad' => null,
            ],
            [
                'documento' => '1000000008',
                'primerNombre' => 'Mateo',
                'primerApellido' => 'Suárez',
                'segundoApellido' => 'Londoño',
                'genero' => Genero::Masculino,
                'fechaNacimiento' => Carbon::parse('1991-12-05'),
                'cargo' => 'Ejecutivo de Ventas',
                'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subYears(5),
                'salario' => 3_400_000,
                // Ya no está activo: es quien alimenta los indicadores de
                // rotación con una salida real.
                'novedad' => function (Vinculacion $vinculacion) use ($hoy): void {
                    $vinculacion->registrarTerminacion(
                        TipoDeSalida::Renuncia,
                        'Mejor oferta laboral — salario',
                        $hoy->clone()->subMonths(3),
                    );
                },
            ],
        ];
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
     * @template TCatalogo of \App\Models\Catalogo
     *
     * @param  class-string<TCatalogo>  $modelo
     * @return TCatalogo
     */
    private function primeraEntidad(string $modelo)
    {
        $entidad = $modelo::query()->paraElegir()->first();

        return $entidad ?? throw new RuntimeException(
            "El catálogo {$modelo} todavía no tiene ninguna entidad activa."
        );
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
