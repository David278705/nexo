<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Dominio\Calculo\CacheDeSaldos;
use App\Inicio\Indicadores\BloqueDeIndicadores;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Terminaciones\TipoDeSalida;
use Illuminate\Support\Carbon;

/**
 * Los seis bloques de indicadores del panel de inicio (F4-04, documento 1,
 * 12.1, bloques 2 a 7).
 *
 * El bloque 1 —«Requiere tu atención»— ya está probado en
 * `PanelDeInicioTest.php` (F4-03); este archivo prueba los seis que faltaban.
 * `usuarioDe()` vive en `RolesYPermisosTest.php`, y Pest carga las funciones de
 * todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Distribuidora El Progreso']);
    $this->contexto = app(ContextoTenant::class);

    // Un lunes de agosto, para que «el mes en curso» tenga días de sobra antes
    // y después de las fechas que arman los casos.
    $this->hoy = Carbon::parse('2026-08-17');
    Carbon::setTestNow($this->hoy->copy()->setTime(9, 0));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Los seis bloques, para el superadministrador, que puede entrar a todos los
 * módulos.
 *
 * @return list<array<string, mixed>>
 */
function bloquesDeIndicadores(): array
{
    $usuario = usuarioDe(test()->empresa, Rol::Superadministrador);
    $bloque = app(BloqueDeIndicadores::class);

    return test()->contexto->paraTenant(
        test()->empresa,
        fn (): array => $bloque->paraElUsuario($usuario, test()->hoy),
    );
}

/**
 * El bloque con ese título, o nulo si no está.
 *
 * @return array<string, mixed>|null
 */
function bloqueLlamado(string $titulo): ?array
{
    foreach (bloquesDeIndicadores() as $bloque) {
        if ($bloque['titulo'] === $titulo) {
            return $bloque;
        }
    }

    return null;
}

/**
 * La tarjeta de un bloque cuya etiqueta contiene ese texto.
 *
 * @param  array<string, mixed>  $bloque
 * @return array<string, mixed>|null
 */
function tarjetaDelBloque(array $bloque, string $texto): ?array
{
    foreach ($bloque['tarjetas'] as $tarjeta) {
        if (str_contains($tarjeta['etiqueta'], $texto)) {
            return $tarjeta;
        }
    }

    return null;
}

describe('el bloque de vacaciones', function () {
    it('trae el pasivo de la planta y las personas con acumulación excesiva desde la caché', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            // Trece meses cerrados al 17 de agosto: 16,25 días, más que el
            // periodo completo de quince.
            Vinculacion::factory()->conIngreso('2025-07-01')->create();
            // Cinco meses: 6,25 días, por debajo del periodo completo.
            Vinculacion::factory()->conIngreso('2026-03-01')->create();

            CacheDeSaldos::refrescar($this->hoy);
        });

        $vacaciones = bloqueLlamado('Vacaciones');

        expect(tarjetaDelBloque($vacaciones, 'Días acumulados por toda la planta')['valor'])
            ->toBe('22,50')
            ->and(tarjetaDelBloque($vacaciones, 'Personas con acumulación excesiva')['valor'])
            ->toBe(1);
    });

    it('cuenta los días disfrutados en lo que va del mes en curso, y no los de meses pasados', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            $vinculacion = Vinculacion::factory()->conIngreso('2024-01-15')->create();

            // Dentro de agosto: lunes 3 a viernes 7, cinco días hábiles.
            Vacacion::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-08-03',
                'fecha_fin' => '2026-08-07',
            ]);

            // En julio: no debe sumar al indicador de agosto.
            Vacacion::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-07-06',
                'fecha_fin' => '2026-07-10',
            ]);

            CacheDeSaldos::refrescar($this->hoy);
        });

        $tarjeta = tarjetaDelBloque(bloqueLlamado('Vacaciones'), 'Días disfrutados en agosto');

        expect($tarjeta['valor'])->toBe('5')
            ->and($tarjeta['nota'])->toBe('En 1 periodo registrado');
    });
});

describe('el bloque de planta', function () {
    it('cuenta los activos de hoy con su variación frente al mes anterior', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            // Tres activos hoy y el mes pasado.
            Vinculacion::factory()->conIngreso('2024-01-15')->count(3)->create();

            // Uno que ingresó esta semana: no estaba el mes pasado.
            Vinculacion::factory()->conIngreso('2026-08-14')->create();
        });

        $tarjeta = tarjetaDelBloque(bloqueLlamado('Planta'), 'Empleados activos');

        expect($tarjeta['valor'])->toBe(4)
            ->and($tarjeta['variacion'])->toBe('1 frente al mes anterior')
            ->and($tarjeta['tendencia'])->toBe('sube');
    });

    it('reparte la planta activa por tipo de contrato', function (): void {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->count(2)->create();
            Vinculacion::factory()->aTerminoFijo()->create();
            Vinculacion::factory()->deObraOLabor()->create();
        });

        $distribucion = bloqueLlamado('Planta')['distribuciones'][0]['items'];
        $porEtiqueta = collect($distribucion)->pluck('cuantos', 'etiqueta');

        expect($porEtiqueta['Término indefinido'])->toBe(2)
            ->and($porEtiqueta['Término fijo'])->toBe(1)
            ->and($porEtiqueta['Obra o labor'])->toBe(1);
    });
});

describe('el bloque de rotación', function () {
    it('cuenta las salidas del mes, separadas en voluntarias e involuntarias', function (): void {
        $this->contexto->paraTenant($this->empresa, function (): void {
            $renuncia = Vinculacion::factory()->conIngreso('2024-01-01')->create();
            $renuncia->registrarTerminacion(TipoDeSalida::Renuncia, null, Carbon::parse('2026-08-10'));

            $despido = Vinculacion::factory()->conIngreso('2024-01-01')->create();
            $despido->registrarTerminacion(TipoDeSalida::SinJustaCausa, 'Cierre de un área o de un proyecto', Carbon::parse('2026-08-12'));

            // Fuera del mes en curso: no debe contar.
            $anterior = Vinculacion::factory()->conIngreso('2024-01-01')->create();
            $anterior->registrarTerminacion(TipoDeSalida::Renuncia, null, Carbon::parse('2026-07-20'));
        });

        $rotacion = bloqueLlamado('Rotación');

        expect(tarjetaDelBloque($rotacion, 'Voluntaria / involuntaria')['valor'])->toBe('1 / 1');
    });

    it('cuenta rotación temprana cuando la salida es antes de los seis meses de vinculación', function (): void {
        $this->contexto->paraTenant($this->empresa, function (): void {
            $reciente = Vinculacion::factory()->conIngreso('2026-06-01')->create();
            $reciente->registrarTerminacion(TipoDeSalida::Renuncia, null, Carbon::parse('2026-08-05'));

            $antigua = Vinculacion::factory()->conIngreso('2020-01-01')->create();
            $antigua->registrarTerminacion(TipoDeSalida::Renuncia, null, Carbon::parse('2026-08-05'));
        });

        $tarjeta = tarjetaDelBloque(bloqueLlamado('Rotación'), 'Rotación temprana');

        expect($tarjeta['valor'])->toBe(1);
    });

    it('lista todos los motivos de salida del mes, sin recortar a un top', function (): void {
        $this->contexto->paraTenant($this->empresa, function (): void {
            $primero = Vinculacion::factory()->conIngreso('2024-01-01')->create();
            $primero->registrarTerminacion(TipoDeSalida::Renuncia, 'Motivos personales o familiares', Carbon::parse('2026-08-06'));

            $segundo = Vinculacion::factory()->conIngreso('2024-01-01')->create();
            $segundo->registrarTerminacion(TipoDeSalida::Renuncia, 'Motivos personales o familiares', Carbon::parse('2026-08-09'));

            $tercero = Vinculacion::factory()->conIngreso('2024-01-01')->create();
            $tercero->registrarTerminacion(TipoDeSalida::Renuncia, 'Estudios', Carbon::parse('2026-08-11'));
        });

        $items = bloqueLlamado('Rotación')['distribuciones'][0]['items'];

        expect($items)->toBe([
            ['etiqueta' => 'Motivos personales o familiares', 'cuantos' => 2],
            ['etiqueta' => 'Estudios', 'cuantos' => 1],
        ]);
    });
});

describe('el bloque de ausentismo', function () {
    it('separa los días perdidos por incapacidad entre origen común y laboral', function (): void {
        $this->contexto->paraTenant($this->empresa, function (): void {
            $vinculacion = Vinculacion::factory()->conIngreso('2024-01-01')->create();

            Incapacidad::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-08-04',
                'fecha_fin' => '2026-08-06',
            ]);

            Incapacidad::factory()->de($vinculacion)->deOrigenLaboral()->create([
                'fecha_inicio' => '2026-08-10',
                'fecha_fin' => '2026-08-11',
            ]);
        });

        $ausentismo = bloqueLlamado('Ausentismo');

        expect(tarjetaDelBloque($ausentismo, 'origen común')['valor'])->toBe(3)
            ->and(tarjetaDelBloque($ausentismo, 'origen laboral')['valor'])->toBe(2)
            ->and(tarjetaDelBloque($ausentismo, 'Incapacidades de agosto')['valor'])->toBe(2);
    });

    it('recorta los días de una incapacidad que empezó el mes pasado a los que caen en agosto', function (): void {
        $this->contexto->paraTenant($this->empresa, function (): void {
            $vinculacion = Vinculacion::factory()->conIngreso('2024-01-01')->create();

            // Del 30 de julio al 3 de agosto: solo el 1, 2 y 3 son de agosto.
            Incapacidad::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-07-30',
                'fecha_fin' => '2026-08-03',
            ]);
        });

        expect(tarjetaDelBloque(bloqueLlamado('Ausentismo'), 'origen común')['valor'])->toBe(3);
    });

    it('cuenta los días perdidos por ausencias no justificadas, sin las suspensiones ni las licencias', function (): void {
        $this->contexto->paraTenant($this->empresa, function (): void {
            $vinculacion = Vinculacion::factory()->conIngreso('2024-01-01')->create();

            Licencia::factory()->de($vinculacion)->create([
                'tipo' => 'ausencia',
                'fecha_inicio' => '2026-08-05',
                'fecha_fin' => '2026-08-06',
            ]);

            // No es una ausencia: no debe sumar.
            Licencia::factory()->de($vinculacion)->create([
                'tipo' => 'suspension',
                'fecha_inicio' => '2026-08-10',
                'fecha_fin' => '2026-08-14',
            ]);
        });

        $tarjeta = tarjetaDelBloque(bloqueLlamado('Ausentismo'), 'Ausencias no justificadas');

        expect($tarjeta['valor'])->toBe(2);
    });

    it('no ofrece el bloque a quien no tiene ni Incapacidades ni Licencias', function (): void {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);
        $bloque = app(BloqueDeIndicadores::class);

        $bloques = $this->contexto->paraTenant(
            $this->empresa,
            fn (): array => $bloque->paraElUsuario($usuario, $this->hoy),
        );

        expect(array_column($bloques, 'titulo'))->not->toContain('Ausentismo');
    });
});

describe('el bloque de composición', function () {
    it('reparte la planta activa por cargo y por género', function (): void {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->create(['cargo' => 'Analista de compras']);
            Vinculacion::factory()->create(['cargo' => 'Analista de compras']);

            $persona = Persona::factory()->create(['genero' => 'femenino']);
            Vinculacion::factory()->de($persona)->create(['cargo' => 'Coordinadora de Operaciones']);
        });

        $composicion = bloqueLlamado('Composición');
        $porCargo = collect($composicion['distribuciones'][0]['items'])->pluck('cuantos', 'etiqueta');
        $porGenero = collect($composicion['distribuciones'][1]['items'])->pluck('cuantos', 'etiqueta');

        expect($porCargo['Analista de compras'])->toBe(2)
            ->and($porGenero['Femenino'])->toBe(1)
            ->and($porGenero->sum())->toBe(3);
    });

    it('deja escrito de qué depende la distribución por rango de edad y de antigüedad', function (): void {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->create();
        });

        expect(bloqueLlamado('Composición')['avisos'][0])->toContain('D.4');
    });

    it('calcula la antigüedad promedio de la planta activa', function (): void {
        $this->contexto->paraTenant($this->empresa, function (): void {
            // Dos años cumplidos al 17 de agosto de 2026.
            Vinculacion::factory()->conIngreso('2024-08-17')->create();
            // Cero meses: ingresó ayer.
            Vinculacion::factory()->conIngreso('2026-08-16')->create();
        });

        $tarjeta = tarjetaDelBloque(bloqueLlamado('Composición'), 'Antigüedad promedio');

        // Promedio de 24 y 0 meses: 12 meses, un año.
        expect($tarjeta['valor'])->toBe('un año');
    });
});

describe('el bloque de movimientos', function () {
    it('cuenta los aumentos y las promociones del mes, con su reparto', function (): void {
        $this->contexto->paraTenant($this->empresa, function (): void {
            $vinculacion = Vinculacion::factory()->conIngreso('2024-01-01')->create();

            Movimiento::factory()->de($vinculacion)->create(['fecha_efectividad' => '2026-08-05']);
            Movimiento::factory()->de($vinculacion)->promocion('Analista', 'Coordinador')->create(['fecha_efectividad' => '2026-08-12']);

            // El mes pasado: no debe contar.
            Movimiento::factory()->de($vinculacion)->create(['fecha_efectividad' => '2026-07-15']);
        });

        $tarjeta = tarjetaDelBloque(bloqueLlamado('Movimientos'), 'Aumentos y promociones en agosto');

        expect($tarjeta['valor'])->toBe(2)
            ->and($tarjeta['nota'])->toBe('1 aumento · 1 promoción');
    });
});

describe('los permisos por módulo', function () {
    it('solo enseña los bloques de los módulos que el usuario tiene', function (): void {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->create();
        });

        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);
        $bloque = app(BloqueDeIndicadores::class);

        $bloques = $this->contexto->paraTenant(
            $this->empresa,
            fn (): array => $bloque->paraElUsuario($usuario, $this->hoy),
        );

        expect(array_column($bloques, 'titulo'))->toBe(['Vacaciones']);
    });

    it('no enseña ningún bloque a quien no tiene ningún módulo', function (): void {
        $usuario = usuarioDe($this->empresa, Rol::Administrador);
        $bloque = app(BloqueDeIndicadores::class);

        $bloques = $this->contexto->paraTenant(
            $this->empresa,
            fn (): array => $bloque->paraElUsuario($usuario, $this->hoy),
        );

        expect($bloques)->toBe([]);
    });
});

describe('el aislamiento', function () {
    it('los indicadores de una empresa no cuentan la planta de otra', function (): void {
        $otra = Tenant::factory()->create(['nombre' => 'Textiles del Norte']);

        $this->contexto->paraTenant($otra, function (): void {
            Vinculacion::factory()->count(5)->create();
        });

        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->create();
        });

        $tarjeta = tarjetaDelBloque(bloqueLlamado('Planta'), 'Empleados activos');

        expect($tarjeta['valor'])->toBe(1);
    });
});

describe('el panel completo abre en menos de dos segundos', function () {
    /**
     * El «hecho cuando» de la tarea: con doscientos empleados, refrescada ya
     * la caché de saldos —lo que hace el proceso diario cada noche—, resolver
     * los seis bloques de indicadores no puede volver a costar los 4,8
     * segundos que F4-03 midió para un solo indicador de vacaciones sin caché.
     */
    it('resuelve los seis bloques con doscientos empleados en menos de dos segundos', function (): void {
        $usuario = usuarioDe($this->empresa, Rol::Superadministrador);

        $this->contexto->paraTenant($this->empresa, function (): void {
            Persona::factory()->count(200)->create()->each(
                fn (Persona $persona) => Vinculacion::factory()->de($persona)->conIngreso('2023-01-15')->create()
            );

            CacheDeSaldos::refrescar($this->hoy);
        });

        $bloque = app(BloqueDeIndicadores::class);

        $arranque = microtime(true);
        $this->contexto->paraTenant(
            $this->empresa,
            fn (): array => $bloque->paraElUsuario($usuario, $this->hoy),
        );
        $tardanza = microtime(true) - $arranque;

        expect($tardanza)->toBeLessThan(2.0);
    });
});
