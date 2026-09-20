<?php

namespace App\Proveedor;

use App\Autorizacion\Rol;
use App\Contratacion\EventoContractual;
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
use App\Models\Pais;
use App\Models\Persona;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Movimientos\TipoDeMovimiento;
use App\Terminaciones\TipoDeSalida;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * La planta de una empresa que lleva años en el sistema.
 *
 * Nació de un hallazgo de David probando la demostración: la planta anterior
 * tenía ocho personas y **una sola novedad cada una**, así que entrar a
 * cualquiera de ellas mostraba una ficha vacía. Servía para enseñar ocho
 * situaciones, no para que el sistema se viera usado. Esta planta es lo
 * contrario: gente con varios años de historia encima —vacaciones de tres
 * temporadas, incapacidades, aumentos, prórrogas— para que cada pantalla tenga
 * algo que enseñar y cada filtro algo que filtrar.
 *
 * **Los datos siguen siendo fijos, no aleatorios.** Es la misma propiedad que
 * la planta anterior cuidaba y por el mismo motivo: un `Faker` sin semilla
 * dejaría el ambiente distinto cada noche, y lo que se quiere es que luzca
 * **igual** de poblado siempre. Lo único relativo a hoy son las fechas, para
 * que el contrato por vencer siga venciendo pronto y la incapacidad siga siendo
 * reciente sin importar qué día se mire.
 *
 * **Ninguna persona tiene dos novedades que se pisen.** No es casualidad: las
 * novedades de cada quien se reparten en meses distintos a propósito, porque
 * desde `HD-03` el sistema rechaza los traslapes y una planta que los tuviera
 * estaría enseñando datos que sus propios formularios no dejarían registrar.
 * `PlantaDeMuestraTest` lo comprueba con el mismo `ComprobacionDeTraslape` que
 * usan los formularios, para que no se separen.
 *
 * **La usan dos sitios y por eso vive aparte**: `AmbienteDeDemostracion`, que
 * escribe la empresa de solo lectura que explora un prospecto, y
 * `DesarrolloSeeder`, que escribe una empresa gemela pero **escribible** para
 * trabajar en la máquina de quien desarrolla. La misma planta en los dos deja
 * de ser casualidad que lo que se prueba sea lo que se enseña.
 *
 * Quien la llama ya tiene que estar dentro de `ContextoTenant::paraTenant()`:
 * esta clase no sabe de empresas, solo escribe con los modelos y deja que el
 * trait `PerteneceATenant` ponga el `tenant_id` que corresponda.
 */
final class PlantaDeMuestra
{
    /**
     * Cuánta gente escribe. Las pruebas y el panel lo leen de aquí en vez de
     * repetir el número, que es lo que obligaba a tocar cuatro archivos cada
     * vez que la planta crecía.
     */
    public const TOTAL = 28;

    /** Cuántos de los de arriba están inactivos, uno por cada tipo de salida. */
    public const INACTIVOS = 7;

    /** @var Collection<int, Eps> */
    private Collection $eps;

    /** @var Collection<int, FondoPension> */
    private Collection $fondos;

    /** @var Collection<int, Arl> */
    private Collection $arls;

    /** @var Collection<int, CajaCompensacion> */
    private Collection $cajas;

    /** @var Collection<int, Banco> */
    private Collection $bancos;

    /** @var Collection<int, Ciudad> */
    private Collection $ciudades;

    /**
     * Escribe la planta entera dentro de la empresa que esté en contexto.
     */
    public function escribir(?Carbon $hoy = null): void
    {
        $hoy ??= Carbon::today();

        $this->cargarCatalogos();

        foreach ($this->planta($hoy) as $indice => $especificacion) {
            $this->escribirPersona($especificacion, $indice, $hoy);
        }
    }

    /**
     * Los catálogos globales, una vez y no una por persona.
     *
     * Se recorren en orden de identificador y se reparten por índice, que es lo
     * que hace que la planta tenga varias EPS y varios bancos en vez de
     * repetir el primero veintiocho veces: un filtro por EPS sin variedad no
     * filtra nada.
     */
    private function cargarCatalogos(): void
    {
        $this->eps = $this->catalogo(Eps::class);
        $this->fondos = $this->catalogo(FondoPension::class);
        $this->arls = $this->catalogo(Arl::class);
        $this->cajas = $this->catalogo(CajaCompensacion::class);
        $this->bancos = $this->catalogo(Banco::class);
        $this->ciudades = $this->ciudadesDeReferencia();
    }

    /**
     * @param  class-string  $modelo
     * @return Collection<int, mixed>
     */
    private function catalogo(string $modelo): Collection
    {
        $entidades = $modelo::query()->paraElegir()->orderBy('id')->get();

        if ($entidades->isEmpty()) {
            throw new RuntimeException("El catálogo {$modelo} todavía no tiene ninguna entidad activa.");
        }

        return $entidades;
    }

    /**
     * Unas cuantas ciudades conocidas, para que la planta no viva entera en
     * Bogotá. Las que falten del catálogo se ignoran sin romper nada.
     *
     * @return Collection<int, Ciudad>
     */
    private function ciudadesDeReferencia(): Collection
    {
        $nombres = ['Bogotá D.C.', 'Medellín', 'Cali', 'Barranquilla', 'Bucaramanga', 'Pereira', 'Manizales'];

        $ciudades = Ciudad::query()->whereIn('nombre', $nombres)->orderBy('id')->get();

        if ($ciudades->isEmpty()) {
            throw new RuntimeException('El catálogo de municipios todavía no tiene ninguna de las ciudades de referencia.');
        }

        return $ciudades;
    }

    /**
     * @param  Collection<int, mixed>  $coleccion
     */
    private function porIndice(Collection $coleccion, int $indice): mixed
    {
        return $coleccion->get($indice % $coleccion->count());
    }

    /**
     * Escribe una persona con su vinculación y toda su historia.
     *
     * @param  array<string, mixed>  $spec
     */
    private function escribirPersona(array $spec, int $indice, Carbon $hoy): void
    {
        $ciudad = $this->porIndice($this->ciudades, $indice);
        $nacimiento = $spec['fechaNacimiento'];

        $persona = Persona::query()->create([
            'tipo_documento' => TipoDocumento::CedulaCiudadania,
            'numero_documento' => $spec['documento'],
            'fecha_expedicion_documento' => $nacimiento->clone()->addYears(18),
            'primer_nombre' => $spec['primerNombre'],
            'segundo_nombre' => $spec['segundoNombre'] ?? null,
            'primer_apellido' => $spec['primerApellido'],
            'segundo_apellido' => $spec['segundoApellido'] ?? null,
            'fecha_nacimiento' => $nacimiento,
            'genero' => $spec['genero'],
            'pais_nacimiento' => Pais::COLOMBIA,
            'ciudad_nacimiento_id' => $ciudad->getKey(),
            'pais_expedicion' => Pais::COLOMBIA,
            'ciudad_expedicion_id' => $ciudad->getKey(),
            'direccion' => 'Calle '.(10 + $indice).' # '.(20 + $indice).'-'.(30 + $indice),
            'ciudad_residencia_id' => $ciudad->getKey(),
            'celular' => $this->celular($indice),
            'correo' => $this->correo($spec),
        ]);

        $vinculacion = $persona->vinculaciones()->create([
            'cargo' => $spec['cargo'],
            'tipo_contrato' => $spec['tipoContrato'],
            'fecha_ingreso' => $spec['fechaIngreso'],
            'fecha_fin' => $spec['fechaFin'] ?? null,
            'salario' => $spec['salario'],
            'bonificacion' => $spec['bonificacion'] ?? 0,
            'eps_id' => $this->porIndice($this->eps, $indice)->getKey(),
            'fondo_pension_id' => $this->porIndice($this->fondos, $indice)->getKey(),
            'arl_id' => $this->porIndice($this->arls, $indice)->getKey(),
            'tipo_riesgo' => $spec['tipoRiesgo'] ?? TipoRiesgo::Uno,
            'caja_compensacion_id' => $this->porIndice($this->cajas, $indice)->getKey(),
            'banco_id' => $this->porIndice($this->bancos, $indice)->getKey(),
        ]);

        $this->escribirHistorial($vinculacion, $spec);
    }

    /**
     * Diez dígitos que empiezan por 3, como cualquier celular colombiano, y
     * distintos entre sí para que buscar por celular no devuelva media planta.
     */
    private function celular(int $indice): string
    {
        return '3'.str_pad((string) (10_000_000 + $indice * 111_111), 9, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function correo(array $spec): string
    {
        $usuario = mb_strtolower($spec['primerNombre'].'.'.$spec['primerApellido']);

        $sinTildes = strtr($usuario, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return $sinTildes.'@empresademo.com.co';
    }

    /**
     * Las prórrogas, las novedades y la salida, en el orden en que ocurrieron.
     *
     * El orden importa: la terminación va de última porque desactiva la
     * vinculación, y `registrarMovimiento()` lee el cargo y el salario que
     * tiene la vinculación en ese momento, así que una cadena de aumentos
     * escrita al revés contaría la historia al revés.
     *
     * @param  array<string, mixed>  $spec
     */
    private function escribirHistorial(Vinculacion $vinculacion, array $spec): void
    {
        foreach ($spec['prorrogas'] ?? [] as $prorroga) {
            $vinculacion->registrarPeriodo(
                $prorroga['evento'] ?? EventoContractual::ProrrogaPactada,
                $prorroga['tipo'] ?? TipoContrato::Fijo,
                $prorroga['inicio'],
                $prorroga['fin'] ?? null,
            );
        }

        foreach ($spec['vacaciones'] ?? [] as [$inicio, $fin]) {
            Vacacion::query()->create([
                'vinculacion_id' => $vinculacion->getKey(),
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
            ]);
        }

        foreach ($spec['incapacidades'] ?? [] as $incapacidad) {
            Incapacidad::query()->create([
                'vinculacion_id' => $vinculacion->getKey(),
                'fecha_inicio' => $incapacidad['inicio'],
                'fecha_fin' => $incapacidad['fin'],
                'codigo_diagnostico' => $incapacidad['diagnostico'],
                'es_prorroga' => $incapacidad['esProrroga'] ?? false,
                'eps_id' => $vinculacion->getAttribute('eps_id'),
                'origen' => $incapacidad['origen'] ?? OrigenIncapacidad::Comun,
            ]);
        }

        foreach ($spec['licencias'] ?? [] as [$tipo, $inicio, $fin]) {
            Licencia::query()->create([
                'vinculacion_id' => $vinculacion->getKey(),
                'tipo' => $tipo,
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
            ]);
        }

        foreach ($spec['movimientos'] ?? [] as $movimiento) {
            $vinculacion->registrarMovimiento(
                $movimiento['tipo'],
                $movimiento['cargo'] ?? null,
                (string) $movimiento['salario'],
                $movimiento['fecha'],
            );
        }

        if (isset($spec['terminacion'])) {
            $vinculacion->registrarTerminacion(
                $spec['terminacion']['tipo'],
                $spec['terminacion']['motivo'],
                $spec['terminacion']['fecha'],
            );
        }
    }

    /**
     * La planta completa: las situaciones que hay que poder enseñar, las
     * salidas —una por cada tipo— y el relleno de veteranos que le da volumen.
     *
     * @return list<array<string, mixed>>
     */
    private function planta(Carbon $hoy): array
    {
        return [
            ...$this->situaciones($hoy),
            ...$this->salidas($hoy),
            ...$this->veteranos($hoy),
        ];
    }

    /**
     * Las once situaciones contractuales que el sistema resuelve, una por
     * persona. Son las que un prospecto tiene que ver funcionando y las que
     * disparan las alertas del capítulo 8.
     *
     * @return list<array<string, mixed>>
     */
    private function situaciones(Carbon $hoy): array
    {
        return [
            // 1. Recién ingresada: en periodo de prueba, dispara sus tres alertas.
            [
                'documento' => '1000000001',
                'primerNombre' => 'Juliana', 'primerApellido' => 'Restrepo', 'segundoApellido' => 'Vélez',
                'genero' => Genero::Femenino, 'fechaNacimiento' => Carbon::parse('1994-03-12'),
                'cargo' => 'Analista de Nómina', 'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subDays(20), 'salario' => 2_600_000,
            ],

            // 2. Fijo por vencer en 25 días, con dos prórrogas encima: preaviso de renovación.
            [
                'documento' => '1000000002',
                'primerNombre' => 'Andrés', 'primerApellido' => 'Gómez', 'segundoApellido' => 'Naranjo',
                'genero' => Genero::Masculino, 'fechaNacimiento' => Carbon::parse('1990-07-08'),
                'cargo' => 'Desarrollador Backend', 'tipoContrato' => TipoContrato::Fijo,
                'fechaIngreso' => $hoy->clone()->subMonths(23), 'fechaFin' => $hoy->clone()->subMonths(11),
                'salario' => 4_800_000,
                'prorrogas' => [
                    ['inicio' => $hoy->clone()->subMonths(11)->addDay(), 'fin' => $hoy->clone()->subDays(1)],
                    ['inicio' => $hoy->clone(), 'fin' => $hoy->clone()->addDays(25)],
                ],
                'vacaciones' => [[$hoy->clone()->subMonths(14), $hoy->clone()->subMonths(14)->addDays(9)]],
                'movimientos' => [
                    ['tipo' => TipoDeMovimiento::AumentoSalarial, 'salario' => 5_200_000, 'fecha' => $hoy->clone()->subMonths(11)],
                ],
            ],

            // 3. Fijo vencido hace 40 días y sin gestionar: el caso que HD-12 bloquea
            //    al intentar darle salida sin aclarar qué contrato rige.
            [
                'documento' => '1000000003',
                'primerNombre' => 'Óscar', 'primerApellido' => 'Villamil', 'segundoApellido' => 'Cruz',
                'genero' => Genero::Masculino, 'fechaNacimiento' => Carbon::parse('1987-02-25'),
                'cargo' => 'Supervisor de Bodega', 'tipoContrato' => TipoContrato::Fijo,
                'fechaIngreso' => $hoy->clone()->subMonths(18), 'fechaFin' => $hoy->clone()->subDays(40),
                'salario' => 3_100_000,
                'incapacidades' => [
                    ['inicio' => $hoy->clone()->subMonths(9), 'fin' => $hoy->clone()->subMonths(9)->addDays(4), 'diagnostico' => 'M54', 'origen' => OrigenIncapacidad::Comun],
                ],
            ],

            // 4. Fijo con tres prórrogas: la siguiente es la cuarta, que es la que
            //    cambia la regla (documento 6, C.1).
            [
                'documento' => '1000000004',
                'primerNombre' => 'Paola', 'segundoNombre' => 'Andrea', 'primerApellido' => 'Ríos', 'segundoApellido' => 'Cifuentes',
                'genero' => Genero::Femenino, 'fechaNacimiento' => Carbon::parse('1992-09-14'),
                'cargo' => 'Asistente Administrativa', 'tipoContrato' => TipoContrato::Fijo,
                'fechaIngreso' => $hoy->clone()->subMonths(24), 'fechaFin' => $hoy->clone()->subMonths(18),
                'salario' => 2_300_000,
                'prorrogas' => [
                    ['inicio' => $hoy->clone()->subMonths(18)->addDay(), 'fin' => $hoy->clone()->subMonths(12)],
                    ['inicio' => $hoy->clone()->subMonths(12)->addDay(), 'fin' => $hoy->clone()->subMonths(6)],
                    ['inicio' => $hoy->clone()->subMonths(6)->addDay(), 'fin' => $hoy->clone()->addMonths(2)],
                ],
                'vacaciones' => [[$hoy->clone()->subMonths(10), $hoy->clone()->subMonths(10)->addDays(6)]],
                'licencias' => [[TipoDeLicencia::Ausencia, $hoy->clone()->subMonths(4), $hoy->clone()->subMonths(4)->addDays(1)]],
            ],

            // 5. Fijo cerca del tope de cuatro años (documento 6, C.2).
            [
                'documento' => '1000000005',
                'primerNombre' => 'Hernán', 'primerApellido' => 'Castaño', 'segundoApellido' => 'Zapata',
                'genero' => Genero::Masculino, 'fechaNacimiento' => Carbon::parse('1985-06-03'),
                'cargo' => 'Jefe de Producción', 'tipoContrato' => TipoContrato::Fijo,
                'fechaIngreso' => $hoy->clone()->subYears(4)->addMonths(2), 'fechaFin' => $hoy->clone()->subYears(3)->addMonths(2),
                'salario' => 6_200_000,
                'prorrogas' => [
                    ['inicio' => $hoy->clone()->subYears(3)->addMonths(2)->addDay(), 'fin' => $hoy->clone()->subYears(2)->addMonths(2)],
                    ['inicio' => $hoy->clone()->subYears(2)->addMonths(2)->addDay(), 'fin' => $hoy->clone()->subYear()->addMonths(2)],
                    ['inicio' => $hoy->clone()->subYear()->addMonths(2)->addDay(), 'fin' => $hoy->clone()->addMonths(2)],
                ],
                'vacaciones' => [
                    [$hoy->clone()->subYears(3), $hoy->clone()->subYears(3)->addDays(14)],
                    [$hoy->clone()->subYears(2)->subMonths(1), $hoy->clone()->subYears(2)->subMonths(1)->addDays(9)],
                ],
                'movimientos' => [
                    ['tipo' => TipoDeMovimiento::AumentoSalarial, 'salario' => 6_800_000, 'fecha' => $hoy->clone()->subMonths(14)],
                ],
            ],

            // 6. Obra o labor de larga duración: el listado bimestral de D.2.
            [
                'documento' => '1000000006',
                'primerNombre' => 'Laura', 'primerApellido' => 'Jiménez', 'segundoApellido' => 'Pardo',
                'genero' => Genero::Femenino, 'fechaNacimiento' => Carbon::parse('1991-12-01'),
                'cargo' => 'Auxiliar de Servicios Generales', 'tipoContrato' => TipoContrato::ObraLabor,
                'fechaIngreso' => $hoy->clone()->subMonths(20), 'salario' => 1_600_000,
                'tipoRiesgo' => TipoRiesgo::Tres,
                'vacaciones' => [[$hoy->clone()->subMonths(8), $hoy->clone()->subMonths(8)->addDays(5)]],
            ],

            // 7. Veterana con historia larga: tres temporadas de vacaciones y una promoción.
            [
                'documento' => '1000000007',
                'primerNombre' => 'Camila', 'primerApellido' => 'Ortiz', 'segundoApellido' => 'Salazar',
                'genero' => Genero::Femenino, 'fechaNacimiento' => Carbon::parse('1988-11-30'),
                'cargo' => 'Coordinadora de Talento Humano', 'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subYears(6), 'salario' => 5_200_000,
                'vacaciones' => [
                    [$hoy->clone()->subYears(4)->startOfMonth(), $hoy->clone()->subYears(4)->startOfMonth()->addDays(14)],
                    [$hoy->clone()->subYears(2)->startOfMonth(), $hoy->clone()->subYears(2)->startOfMonth()->addDays(9)],
                    [$hoy->clone()->subMonths(7)->startOfMonth(), $hoy->clone()->subMonths(7)->startOfMonth()->addDays(9)],
                ],
                'incapacidades' => [
                    ['inicio' => $hoy->clone()->subMonths(19), 'fin' => $hoy->clone()->subMonths(19)->addDays(2), 'diagnostico' => 'A09', 'origen' => OrigenIncapacidad::Comun],
                ],
                'movimientos' => [
                    ['tipo' => TipoDeMovimiento::AumentoSalarial, 'salario' => 5_700_000, 'fecha' => $hoy->clone()->subYears(3)],
                    ['tipo' => TipoDeMovimiento::Promocion, 'cargo' => 'Jefe de Talento Humano', 'salario' => 7_100_000, 'fecha' => $hoy->clone()->subMonths(15)],
                ],
            ],

            // 8. Incapacidad reciente con su prórroga: dos filas encadenadas.
            [
                'documento' => '1000000008',
                'primerNombre' => 'Santiago', 'primerApellido' => 'Herrera', 'segundoApellido' => 'Buitrago',
                'genero' => Genero::Masculino, 'fechaNacimiento' => Carbon::parse('1996-05-19'),
                'cargo' => 'Auxiliar Contable', 'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subMonths(30), 'salario' => 2_100_000,
                'incapacidades' => [
                    ['inicio' => $hoy->clone()->subDays(21), 'fin' => $hoy->clone()->subDays(15), 'diagnostico' => 'J00', 'origen' => OrigenIncapacidad::Comun],
                    ['inicio' => $hoy->clone()->subDays(14), 'fin' => $hoy->clone()->subDays(8), 'diagnostico' => 'J00', 'origen' => OrigenIncapacidad::Comun, 'esProrroga' => true],
                ],
                'vacaciones' => [[$hoy->clone()->subMonths(11), $hoy->clone()->subMonths(11)->addDays(9)]],
            ],

            // 9. Licencia no remunerada vigente hoy.
            [
                'documento' => '1000000009',
                'primerNombre' => 'Valentina', 'primerApellido' => 'Cárdenas', 'segundoApellido' => 'Mejía',
                'genero' => Genero::Femenino, 'fechaNacimiento' => Carbon::parse('1993-01-22'),
                'cargo' => 'Analista de Mercadeo', 'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subYears(2), 'salario' => 3_000_000,
                'licencias' => [[TipoDeLicencia::LicenciaNoRemunerada, $hoy->clone()->subDays(2), $hoy->clone()->addDays(3)]],
                'vacaciones' => [[$hoy->clone()->subMonths(9), $hoy->clone()->subMonths(9)->addDays(9)]],
            ],

            // 10. Suspensión: la que descuenta saldo de vacaciones si la empresa
            //     tiene activo `suspensiones_afectan_vacaciones`.
            [
                'documento' => '1000000010',
                'primerNombre' => 'Ricardo', 'primerApellido' => 'Peña', 'segundoApellido' => 'Lozano',
                'genero' => Genero::Masculino, 'fechaNacimiento' => Carbon::parse('1989-08-17'),
                'cargo' => 'Operario de Máquina', 'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subYears(3)->subMonths(4), 'salario' => 1_900_000,
                'tipoRiesgo' => TipoRiesgo::Cuatro,
                'licencias' => [[TipoDeLicencia::Suspension, $hoy->clone()->subMonths(5), $hoy->clone()->subMonths(5)->addDays(2)]],
                'incapacidades' => [
                    ['inicio' => $hoy->clone()->subMonths(13), 'fin' => $hoy->clone()->subMonths(13)->addDays(20), 'diagnostico' => 'S62', 'origen' => OrigenIncapacidad::Laboral],
                ],
                'vacaciones' => [[$hoy->clone()->subMonths(21), $hoy->clone()->subMonths(21)->addDays(9)]],
            ],

            // 11. Gerente con varios aumentos: la pestaña de movimientos con historia.
            [
                'documento' => '1000000011',
                'primerNombre' => 'Diego', 'primerApellido' => 'Martínez', 'segundoApellido' => 'Rincón',
                'genero' => Genero::Masculino, 'fechaNacimiento' => Carbon::parse('1983-04-27'),
                'cargo' => 'Ejecutivo de Cuenta', 'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subYears(7), 'salario' => 4_500_000, 'bonificacion' => 500_000,
                'vacaciones' => [
                    [$hoy->clone()->subYears(3)->startOfMonth()->addMonths(2), $hoy->clone()->subYears(3)->startOfMonth()->addMonths(2)->addDays(14)],
                    [$hoy->clone()->subMonths(16)->startOfMonth(), $hoy->clone()->subMonths(16)->startOfMonth()->addDays(9)],
                ],
                'movimientos' => [
                    ['tipo' => TipoDeMovimiento::AumentoSalarial, 'salario' => 5_400_000, 'fecha' => $hoy->clone()->subYears(5)],
                    ['tipo' => TipoDeMovimiento::Promocion, 'cargo' => 'Gerente Comercial', 'salario' => 7_500_000, 'fecha' => $hoy->clone()->subYears(2)],
                    ['tipo' => TipoDeMovimiento::AumentoSalarial, 'salario' => 8_200_000, 'fecha' => $hoy->clone()->subMonths(6)],
                ],
            ],
        ];
    }

    /**
     * Una salida por cada uno de los siete tipos del documento 1, 7.2.
     *
     * Están aquí y no repartidas entre los veteranos porque el listado de
     * terminaciones y su filtro por tipo necesitan justamente eso: que los
     * siete existan. El motivo va escrito porque cinco de los siete lo exigen;
     * los dos que no tienen submotivos lo resuelven solos en
     * `TipoDeSalida::motivoResultante()`, así que se les pasa `null`.
     *
     * @return list<array<string, mixed>>
     */
    private function salidas(Carbon $hoy): array
    {
        $salidas = [
            ['tipo' => TipoDeSalida::Renuncia, 'motivo' => 'Mejor oferta laboral — salario', 'cargo' => 'Ejecutivo de Ventas', 'contrato' => TipoContrato::Indefinido, 'meses' => 62],
            ['tipo' => TipoDeSalida::MutuoAcuerdo, 'motivo' => null, 'cargo' => 'Diseñadora Gráfica', 'contrato' => TipoContrato::Indefinido, 'meses' => 40],
            ['tipo' => TipoDeSalida::PeriodoDePrueba, 'motivo' => null, 'cargo' => 'Auxiliar de Archivo', 'contrato' => TipoContrato::Indefinido, 'meses' => 3],
            ['tipo' => TipoDeSalida::DespidoConJustaCausa, 'motivo' => 'Incumplimiento grave de obligaciones', 'cargo' => 'Conductor', 'contrato' => TipoContrato::Indefinido, 'meses' => 28],
            ['tipo' => TipoDeSalida::SinJustaCausa, 'motivo' => 'Reestructuración del área', 'cargo' => 'Analista de Compras', 'contrato' => TipoContrato::Indefinido, 'meses' => 34],
            ['tipo' => TipoDeSalida::ExpiracionDelPlazo, 'motivo' => 'No se renovó el contrato', 'cargo' => 'Practicante de Sistemas', 'contrato' => TipoContrato::Fijo, 'meses' => 12],
            ['tipo' => TipoDeSalida::ObraTerminada, 'motivo' => 'Terminó la obra contratada', 'cargo' => 'Ayudante de Obra', 'contrato' => TipoContrato::ObraLabor, 'meses' => 9],
        ];

        $nombres = [
            ['Mateo', 'Suárez', 'Londoño', Genero::Masculino, '1990-10-05'],
            ['Daniela', 'Moreno', 'Acosta', Genero::Femenino, '1995-02-18'],
            ['Julián', 'Cortés', 'Bermúdez', Genero::Masculino, '1999-07-23'],
            ['Fernando', 'Quintero', 'Arias', Genero::Masculino, '1986-03-09'],
            ['Marcela', 'Duarte', 'Sanabria', Genero::Femenino, '1991-11-11'],
            ['Kevin', 'Ospina', 'Grisales', Genero::Masculino, '2000-05-30'],
            ['Wilson', 'Beltrán', 'Cárdenas', Genero::Masculino, '1984-09-02'],
        ];

        $planta = [];

        foreach ($salidas as $indice => $salida) {
            [$nombre, $apellido, $segundoApellido, $genero, $nacimiento] = $nombres[$indice];

            // La salida cae dentro del contrato, nunca después de que venciera:
            // una terminación en un hueco sin contrato es justo lo que HD-12
            // impide registrar, y la planta no puede contradecir a la regla.
            $ingreso = $hoy->clone()->subMonths($salida['meses']);
            $fechaSalida = $hoy->clone()->subMonths(intdiv($salida['meses'], 4) + 1);

            $planta[] = [
                'documento' => '20000000'.str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT),
                'primerNombre' => $nombre, 'primerApellido' => $apellido, 'segundoApellido' => $segundoApellido,
                'genero' => $genero, 'fechaNacimiento' => Carbon::parse($nacimiento),
                'cargo' => $salida['cargo'], 'tipoContrato' => $salida['contrato'],
                'fechaIngreso' => $ingreso,
                'fechaFin' => $salida['contrato'] === TipoContrato::Fijo ? $fechaSalida->clone()->addMonths(2) : null,
                'salario' => 1_800_000 + $indice * 400_000,
                'vacaciones' => $salida['meses'] > 18
                    ? [[$ingreso->clone()->addMonths(14), $ingreso->clone()->addMonths(14)->addDays(9)]]
                    : [],
                'terminacion' => ['tipo' => $salida['tipo'], 'motivo' => $salida['motivo'], 'fecha' => $fechaSalida],
            ];
        }

        return $planta;
    }

    /**
     * Los diez veteranos que le dan volumen a la planta.
     *
     * No están escritos uno por uno: se derivan del índice, que es lo que
     * permite tener diez personas con tres años de historia cada una sin
     * cuatrocientas líneas de literales. Derivar no es lo mismo que sortear
     * —aquí no hay azar—: el mismo índice produce siempre la misma persona con
     * las mismas fechas, que es la propiedad que la demostración necesita.
     *
     * **Las novedades se reparten en meses distintos a propósito.** Vacaciones
     * en los meses 30, 18 y 6 hacia atrás; incapacidad en el 24; licencia en el
     * 9. Ninguna se pisa con otra, ni siquiera entre tipos distintos, que es lo
     * que `ComprobacionDeTraslape` exige desde HD-03.
     *
     * @return list<array<string, mixed>>
     */
    private function veteranos(Carbon $hoy): array
    {
        $gente = [
            ['Adriana', 'Rojas', 'Muñoz', Genero::Femenino, '1987-01-15', 'Contadora', 4_900_000],
            ['Felipe', 'Cárdenas', 'Osorio', Genero::Masculino, '1992-06-21', 'Analista de Datos', 4_200_000],
            ['Natalia', 'Guerrero', 'Pineda', Genero::Femenino, '1990-04-03', 'Ejecutiva de Servicio', 2_800_000],
            ['Sebastián', 'Ramírez', 'Toro', Genero::Masculino, '1994-08-28', 'Desarrollador Frontend', 4_400_000],
            ['Carolina', 'Mendoza', 'Vargas', Genero::Femenino, '1986-12-07', 'Jefe de Compras', 5_600_000],
            ['Iván', 'Molina', 'Restrepo', Genero::Masculino, '1983-05-16', 'Coordinador de Logística', 3_900_000],
            ['Lorena', 'Sánchez', 'Ibáñez', Genero::Femenino, '1997-03-24', 'Auxiliar de Cartera', 2_000_000],
            ['Gustavo', 'Alzate', 'Cano', Genero::Masculino, '1981-10-12', 'Director Administrativo', 9_100_000],
            ['Mónica', 'Valencia', 'Agudelo', Genero::Femenino, '1993-09-06', 'Analista de Selección', 3_200_000],
            ['Tatiana', 'Bermúdez', 'Escobar', Genero::Femenino, '1995-07-19', 'Asistente de Gerencia', 2_700_000],
        ];

        $diagnosticos = ['J06', 'M54', 'K29', 'R51', 'A09'];

        $planta = [];

        foreach ($gente as $indice => [$nombre, $apellido, $segundoApellido, $genero, $nacimiento, $cargo, $salario]) {
            $antiguedad = 36 + $indice * 7;

            $planta[] = [
                'documento' => '30000000'.str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT),
                'primerNombre' => $nombre, 'primerApellido' => $apellido, 'segundoApellido' => $segundoApellido,
                'genero' => $genero, 'fechaNacimiento' => Carbon::parse($nacimiento),
                'cargo' => $cargo, 'tipoContrato' => TipoContrato::Indefinido,
                'fechaIngreso' => $hoy->clone()->subMonths($antiguedad),
                'salario' => $salario,
                'bonificacion' => $indice % 3 === 0 ? 300_000 : 0,
                'vacaciones' => [
                    [$hoy->clone()->subMonths(30)->startOfMonth()->addDays($indice), $hoy->clone()->subMonths(30)->startOfMonth()->addDays($indice + 9)],
                    [$hoy->clone()->subMonths(18)->startOfMonth()->addDays($indice), $hoy->clone()->subMonths(18)->startOfMonth()->addDays($indice + 6)],
                    [$hoy->clone()->subMonths(6)->startOfMonth()->addDays($indice), $hoy->clone()->subMonths(6)->startOfMonth()->addDays($indice + 9)],
                ],
                'incapacidades' => [
                    [
                        'inicio' => $hoy->clone()->subMonths(24)->startOfMonth()->addDays($indice),
                        'fin' => $hoy->clone()->subMonths(24)->startOfMonth()->addDays($indice + 2 + $indice % 4),
                        'diagnostico' => $diagnosticos[$indice % count($diagnosticos)],
                        'origen' => $indice % 5 === 0 ? OrigenIncapacidad::Laboral : OrigenIncapacidad::Comun,
                    ],
                ],
                'licencias' => $indice % 2 === 0
                    ? [[
                        $indice % 4 === 0 ? TipoDeLicencia::Ausencia : TipoDeLicencia::LicenciaNoRemunerada,
                        $hoy->clone()->subMonths(9)->startOfMonth()->addDays($indice),
                        $hoy->clone()->subMonths(9)->startOfMonth()->addDays($indice + 1),
                    ]]
                    : [],
                'movimientos' => [
                    [
                        'tipo' => TipoDeMovimiento::AumentoSalarial,
                        'salario' => (int) round($salario * 1.08),
                        'fecha' => $hoy->clone()->subMonths(intdiv($antiguedad, 2)),
                    ],
                ],
            ];
        }

        return $planta;
    }

    /**
     * El rol que le corresponde a la cuenta principal de una empresa escrita
     * con esta planta. Vive aquí para que el seeder de desarrollo y el ambiente
     * de demostración no lo escriban cada uno por su lado.
     */
    public static function rolDeLaCuentaPrincipal(): Rol
    {
        return Rol::Superadministrador;
    }
}
