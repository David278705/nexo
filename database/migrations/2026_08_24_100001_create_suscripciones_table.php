<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La suscripción de cada empresa cliente (documento 4, capítulo 6 y 17.2):
 * «empresa, plan, fecha de inicio, próxima fecha de cobro, estado, medio de
 * pago».
 *
 * **Una por empresa, y el índice único parcial lo hace cumplir.** No es un
 * histórico de contratos: es lo que hoy se le cobra a ese cliente. El cambio de
 * plan —«con la fecha desde la que aplica», dice el capítulo 6— se guarda en
 * `plan_aplica_desde` y su historia queda en la auditoría, que ya registra el
 * plan anterior y el nuevo con su autor y su fecha; una segunda tabla de tramos
 * sería una segunda versión de un hecho ya registrado.
 *
 * **No lleva el trait `PerteneceATenant` ni política de RLS**, igual que
 * `invitaciones` y `accesos_de_prueba`: es una tabla del panel del proveedor.
 * La suscripción se lee y se escribe con el `owner`, que no tiene empresa en
 * contexto, y ninguna pantalla del cliente la muestra —el capítulo 6 es del
 * panel—. Lo que sí llega al cliente es la constancia: el observador de
 * auditoría escribe cada suspensión y cada reactivación en la auditoría **de
 * esa empresa**, porque la línea toma el `tenant_id` del propio registro.
 *
 * La suspensión guarda las cuatro cosas que hacen falta para que sea una
 * constancia y no un estado: cuándo, por qué, y lo mismo al reactivar. El
 * capítulo 6 lo pide con esas palabras —«suspensión y reactivación, con motivo y
 * constancia»— y advierte lo que la suspensión **no** es: «bloquea el acceso al
 * sistema pero no elimina nada y no detiene los cálculos internos. Al reactivar,
 * todo sigue donde estaba».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripciones', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('plan_id')->constrained('planes');

            // Desde cuándo es cliente de pago, que no cambia nunca, y desde
            // cuándo le aplica el plan que tiene hoy, que cambia con cada
            // cambio de plan. Al registrarla son la misma fecha.
            $tabla->date('inicia_el');
            $tabla->date('plan_aplica_desde');

            $tabla->date('proxima_fecha_cobro');
            $tabla->string('medio_de_pago');

            $tabla->string('estado')->default('activa');

            $tabla->timestamp('suspendida_en')->nullable();
            $tabla->text('motivo_de_suspension')->nullable();
            $tabla->timestamp('reactivada_en')->nullable();
            $tabla->text('motivo_de_reactivacion')->nullable();

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['estado', 'proxima_fecha_cobro']);
        });

        // Una empresa no puede tener dos suscripciones vivas. El índice es
        // parcial por el borrado lógico: una suscripción eliminada no le
        // impide a la empresa volver a tener una.
        DB::statement(
            'create unique index suscripciones_una_por_empresa
                on suscripciones (tenant_id)
                where deleted_at is null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripciones');
    }
};
