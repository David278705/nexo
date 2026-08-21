<?php

use App\Dominio\Calculo\MotorDeCalculo;
use App\Dominio\Calculo\PeriodoDePrueba;
use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Models\Festivo;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Carbon;

/**
 * Los diez casos de prueba obligatorios del motor de cálculo
 * (documento 2, sección 4.2).
 *
 * **Los diez se escribieron antes que el motor y fallando**, que es la regla del
 * motor y fue la tarea F2-01. F2-02 escribió el cálculo sin tocar este archivo y
 * los diez pasan; desde entonces `composer verificar` los corre como al resto,
 * sin exclusiones. Siguen en el grupo `motor` para poder correr el motor solo
 * con `pest --group=motor`.
 *
 * Si un caso de aquí estorba, la conversación es sobre la regla laboral, nunca
 * sobre la prueba.
 *
 * Dos reglas del motor no están escritas en ninguna parte y salen de leer los
 * casos obligatorios entre ellos. Van anotadas donde aparecen:
 *
 * 1. **La acumulación se cuenta en meses comerciales de treinta días.** Lo dice
 *    el caso de los cuatro días de licencia y lo confirma el de la licencia de
 *    marzo a abril.
 * 2. **El mes se causa completo al cerrarse, no día a día.** El saldo del 31 de
 *    julio y el proyectado al 28 de agosto son el mismo número.
 */
beforeEach(function () {
    // Sin sábado laboral, que es lo corriente. El caso del festivo en sábado
    // crea la suya con el parámetro activo, porque es justo lo que mide.
    $this->empresa = Tenant::factory()->create(['sabado_laboral' => false]);
});

/**
 * Ejecuta algo dentro de la empresa de la prueba.
 */
function enLaEmpresaDelMotor(Closure $accion): mixed
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, $accion);
}

/**
 * Una vinculación con saldo de carga inicial: al 31 de enero de 2026 tenía 5,25
 * días. Es el escenario de los dos casos del saldo, y está armado para que la
 * cuenta no dependa de ningún mes a medias: del corte al 31 de julio hay seis
 * meses completos, que son 7,5 días, y 5,25 + 7,5 son los 12,75 del documento.
 */
function vinculacionConSaldoDeCorte(): Vinculacion
{
    return enLaEmpresaDelMotor(fn () => Vinculacion::factory()->conIngreso('2024-03-01')->create([
        'saldo_inicial_vacaciones' => 5.25,
        'fecha_corte_saldo' => Carbon::parse('2026-01-31'),
    ]));
}

it('cuenta tres días en una incapacidad del 14 al 16 de julio', function () {
    // Días corridos: el primero y el último cuentan. El error clásico es restar
    // las dos fechas y devolver dos.
    expect(MotorDeCalculo::diasCorridos(Carbon::parse('2026-07-14'), Carbon::parse('2026-07-16')))
        ->toBe(3);
})->group('motor');

it('cuenta seis días en una licencia de jueves a martes, con el domingo dentro', function () {
    $jueves = Carbon::parse('2026-07-02');
    $martes = Carbon::parse('2026-07-07');

    // Jueves, viernes, sábado, domingo, lunes y martes. El domingo cuenta: las
    // licencias y las suspensiones van en días corridos, y esa es justamente la
    // regla que no se puede confundir con la de vacaciones.
    expect(MotorDeCalculo::diasCorridos($jueves, $martes))->toBe(6);

    // Los días de licencia y los de suspensión son el mismo número porque son la
    // misma regla, no dos cuentas distintas que casualmente coinciden.
    expect(MotorDeCalculo::diasSuspendidosDelMes($jueves, $martes, Carbon::parse('2026-07-01')))
        ->toBe(6);
})->group('motor');

it('reduce a la mitad la acumulación de marzo y la de abril con una licencia del 16 de marzo al 15 de abril', function () {
    $inicio = Carbon::parse('2026-03-16');
    $fin = Carbon::parse('2026-04-15');

    // Quince días de cada mes en base comercial de treinta. En marzo son del 16
    // al 30: el día 31 no cuenta, y es lo único que hace que las dos mitades den
    // exactamente la mitad, como dice el caso obligatorio.
    expect(MotorDeCalculo::diasSuspendidosDelMes($inicio, $fin, Carbon::parse('2026-03-01')))->toBe(15)
        ->and(MotorDeCalculo::diasSuspendidosDelMes($inicio, $fin, Carbon::parse('2026-04-01')))->toBe(15);

    expect(MotorDeCalculo::acumulacionProporcional(15))->toEqualWithDelta(0.625, 0.0001);
})->group('motor');

it('acumula proporcional a veintiséis días, no a treinta, con cuatro días de licencia en el mes', function () {
    // 1,25 × 26 ÷ 30. La base es treinta días y no la duración real del mes.
    expect(MotorDeCalculo::acumulacionProporcional(4))->toEqualWithDelta(1.0833333, 0.0001);

    // Sin suspensiones, el mes completo: 1,25 días del artículo 186.
    $vinculacion = enLaEmpresaDelMotor(fn () => Vinculacion::factory()->conIngreso('2025-01-15')->create());

    expect(enLaEmpresaDelMotor(fn () => MotorDeCalculo::acumulacionMes($vinculacion, Carbon::parse('2026-03-01'))))
        ->toEqualWithDelta(1.25, 0.0001);
})->group('motor');

it('hace cumplir el primer mes el 14 de mayo a quien ingresó el 15 de abril', function () {
    $ingreso = Carbon::parse('2026-04-15');

    // La víspera del mismo día del mes siguiente, porque el día de ingreso es el
    // primer día trabajado. De aquí salen las alertas de periodo de prueba.
    expect(MotorDeCalculo::cumpleMesesEl($ingreso, 1)->toDateString())->toBe('2026-05-14')
        ->and(MotorDeCalculo::cumpleMesesEl($ingreso, 2)->toDateString())->toBe('2026-06-14');
})->group('motor');

it('da un saldo de 12,75 días al 31 de julio', function () {
    $vinculacion = vinculacionConSaldoDeCorte();

    $saldo = enLaEmpresaDelMotor(
        fn () => SaldoDeVacaciones::de($vinculacion)->al(Carbon::parse('2026-07-31'))
    );

    // 5,25 de la carga inicial más seis meses cerrados a 1,25.
    expect($saldo)->toEqualWithDelta(12.75, 0.0001);
})->group('motor');

it('avisa que faltan 1,25 días a quien pide catorce para el 28 de agosto con 12,75 proyectados', function () {
    $vinculacion = vinculacionConSaldoDeCorte();
    $disfrute = Carbon::parse('2026-08-28');

    [$proyectado, $faltante] = enLaEmpresaDelMotor(fn () => [
        SaldoDeVacaciones::de($vinculacion)->al($disfrute),
        SaldoDeVacaciones::de($vinculacion)->faltantePara(14, $disfrute),
    ]);

    // El saldo se proyecta a la fecha de disfrute y no a la de hoy. Sigue siendo
    // 12,75 porque agosto no ha cerrado: el mes se causa completo al terminar.
    expect($proyectado)->toEqualWithDelta(12.75, 0.0001)
        ->and($faltante)->toEqualWithDelta(1.25, 0.0001);
})->group('motor');

it('da un periodo de prueba de seis días en un contrato fijo del 15 de abril al 14 de mayo', function () {
    $vinculacion = enLaEmpresaDelMotor(
        fn () => Vinculacion::factory()->aTerminoFijo('2026-04-15', '2026-05-14')->create()
    );

    $prueba = PeriodoDePrueba::de($vinculacion);

    // Treinta días pactados, quinta parte: seis, contando el día de ingreso.
    expect($prueba->dias())->toBe(6)
        ->and($prueba->terminaEl()->toDateString())->toBe('2026-04-20');

    // Y la quinta parte nunca pasa de dos meses, que es el máximo legal absoluto.
    $largo = enLaEmpresaDelMotor(
        fn () => Vinculacion::factory()->aTerminoFijo('2026-04-15', '2029-04-14')->create()
    );

    expect(PeriodoDePrueba::de($largo)->terminaEl()->toDateString())->toBe('2026-06-14');
})->group('motor');

it('envía el viernes, «en dos días», una alerta programada para el domingo', function () {
    $domingo = Carbon::parse('2026-07-05');

    $envio = MotorDeCalculo::diaHabilAnteriorParaAlertas($domingo);

    // El sábado tampoco es hábil para una alerta, tenga la empresa el sábado
    // laboral o no: el correo lo lee alguien un día de oficina.
    expect($envio->toDateString())->toBe('2026-07-03');

    // Dos días, no tres: la anticipación es la diferencia entre las fechas y no
    // los días corridos entre ellas. El texto del correo lo escribe F3-02 con
    // este número.
    expect(MotorDeCalculo::diasDeAnticipacion($envio, $domingo))->toBe(2);
})->group('motor');

it('no cuenta como día de vacaciones un festivo que cae en sábado, ni con sábado laboral', function () {
    $empresa = Tenant::factory()->create(['sabado_laboral' => true]);

    Festivo::registrar(Carbon::parse('2027-05-01'), 'Día del Trabajo');

    // Viernes, sábado festivo, domingo y lunes: solo el viernes y el lunes.
    expect(MotorDeCalculo::diasHabilesVacaciones(
        Carbon::parse('2027-04-30'),
        Carbon::parse('2027-05-03'),
        $empresa,
    ))->toBe(2);

    // Y el sábado sí cuenta cuando no es festivo, que es lo que hace que el caso
    // anterior mida el festivo y no el parámetro.
    expect(MotorDeCalculo::diasHabilesVacaciones(
        Carbon::parse('2027-05-14'),
        Carbon::parse('2027-05-17'),
        $empresa,
    ))->toBe(3);
})->group('motor');

/*
|--------------------------------------------------------------------------
| Los casos que agregó F2-02
|--------------------------------------------------------------------------
|
| Los diez de arriba son los obligatorios del documento y no se tocan. Estos
| cubren lo que el motor tuvo que decidir para que aquellos pasaran, que es lo
| que la regla del motor manda hacer: «si un caso nuevo aparece durante el
| trabajo, se agrega a la batería, no se resuelve a mano».
|
*/

it('deja fuera el sábado cuando la empresa no lo tiene laboral', function () {
    // El mismo tramo del caso obligatorio, en la empresa corriente: viernes,
    // sábado y lunes son dos días hábiles, no tres.
    expect(MotorDeCalculo::diasHabilesVacaciones(
        Carbon::parse('2027-05-14'),
        Carbon::parse('2027-05-17'),
        $this->empresa,
    ))->toBe(2);
})->group('motor');

it('cuenta un día cuando la novedad empieza y termina el mismo día', function () {
    $dia = Carbon::parse('2026-07-14');

    expect(MotorDeCalculo::diasCorridos($dia, $dia))->toBe(1);
})->group('motor');

it('cuenta treinta días comerciales en un mes cubierto entero, aunque tenga veintiocho', function () {
    // La otra cara de que el día 31 no cuente. Sin esto, una licencia de todo
    // febrero dejaría dos días acumulando sin que nadie hubiera trabajado.
    $dias = MotorDeCalculo::diasSuspendidosDelMes(
        Carbon::parse('2026-02-01'),
        Carbon::parse('2026-02-28'),
        Carbon::parse('2026-02-01'),
    );

    expect($dias)->toBe(30)
        ->and(MotorDeCalculo::acumulacionProporcional($dias))->toEqualWithDelta(0.0, 0.0001);
})->group('motor');

it('envía el mismo día una alerta programada para un día de oficina', function () {
    // Solo se corre lo que cae en sábado, domingo o festivo (documento 1, 9.1).
    $miercoles = Carbon::parse('2026-07-08');

    expect(MotorDeCalculo::diaHabilAnteriorParaAlertas($miercoles)->toDateString())->toBe('2026-07-08')
        ->and(MotorDeCalculo::diasDeAnticipacion($miercoles, $miercoles))->toBe(0);
})->group('motor');

it('corre al día hábil anterior una alerta programada para un festivo entre semana', function () {
    // El 20 de julio de 2026 es lunes y es festivo: la alerta sale el viernes.
    Festivo::registrar(Carbon::parse('2026-07-20'), 'Día de la Independencia');

    expect(MotorDeCalculo::diaHabilAnteriorParaAlertas(Carbon::parse('2026-07-20'))->toDateString())
        ->toBe('2026-07-17');
})->group('motor');

it('prorratea el primer mes de quien ingresa a mitad de mes y no trae carga inicial', function () {
    // Sin fecha de corte, la acumulación corre desde el ingreso y el saldo
    // inicial es cero. Del 16 al 30 de marzo son quince días comerciales: media
    // acumulación. Abril y mayo enteros, a 1,25.
    $vinculacion = enLaEmpresaDelMotor(fn () => Vinculacion::factory()->conIngreso('2026-03-16')->create());

    $saldo = enLaEmpresaDelMotor(
        fn () => SaldoDeVacaciones::de($vinculacion)->al(Carbon::parse('2026-05-31'))
    );

    expect($saldo)->toEqualWithDelta(3.125, 0.0001);
})->group('motor');

it('deja de acumular el día que la persona sale', function () {
    $vinculacion = enLaEmpresaDelMotor(
        fn () => Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-03-15')->terminada()->create()
    );

    $saldo = enLaEmpresaDelMotor(
        fn () => SaldoDeVacaciones::de($vinculacion)->al(Carbon::parse('2026-05-31'))
    );

    // Enero y febrero completos, media acumulación en marzo, y nada después:
    // el saldo de quien se fue no sigue creciendo.
    expect($saldo)->toEqualWithDelta(3.125, 0.0001);
})->group('motor');

it('da dos meses de periodo de prueba en un contrato a término indefinido', function () {
    $vinculacion = enLaEmpresaDelMotor(fn () => Vinculacion::factory()->conIngreso('2026-04-15')->create());

    // Artículo 78 del CST: dos meses, que terminan la víspera del mismo día.
    expect(PeriodoDePrueba::de($vinculacion)->terminaEl()->toDateString())->toBe('2026-06-14')
        ->and(PeriodoDePrueba::de($vinculacion)->dias())->toBe(61);
})->group('motor');
