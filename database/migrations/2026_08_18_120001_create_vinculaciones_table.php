<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La vinculación: la relación laboral, no la persona.
 *
 * La otra mitad de la sección 3.2 del documento de arquitectura. Nace y muere
 * con cada contratación, y **una persona puede tener varias a lo largo del
 * tiempo**: si alguien se retira y vuelve, se crea una vinculación nueva, no se
 * reactiva la anterior ni se duplican sus datos personales (documento 1, 5.6).
 *
 * Por eso aquí no hay ningún índice único sobre el documento: el documento ni
 * siquiera está en esta tabla, está en `personas`.
 *
 * ## Lo que no está aquí, y dónde está
 *
 * - **El historial del contrato** —periodo inicial, prórrogas, cambio a
 *   indefinido— es la tabla `periodos_contractuales` de la tarea F1-02.
 *   `tipo_contrato` y `fecha_fin` guardan lo vigente hoy, que es lo que se ve en
 *   los listados; el tramo a tramo es de la otra tabla.
 * - **El saldo de vacaciones no es una columna.** Es la regla 2 del proyecto:
 *   se calcula con `saldo_inicial + acumulación − disfrute − suspensiones`.
 *   `saldo_inicial_vacaciones` y `fecha_corte_saldo` sí están, y son otra cosa:
 *   el punto de partida que trae la carga inicial (documento 2, 3.4), no un
 *   número que se sume y se reste.
 * - **La salida** es el módulo de terminaciones (F2-09). Aquí solo queda el
 *   `estado`, que cambia a inactivo cuando esa salida se registra.
 *
 * ## El estado no lo digita nadie
 *
 * Sección 5.3 del documento 1: al crear queda **activo**, y solo pasa a
 * inactivo al registrar la salida. Por eso la columna tiene su valor por
 * defecto en la base de datos y no en el formulario.
 *
 * ## Lo que queda nulo a la espera de la decisión D.5
 *
 * Las cinco claves foráneas de seguridad social y banco, y `tipo_riesgo`,
 * admiten nulos porque el contenido de esas cinco listas es la decisión D.5 de
 * `docs/06-decisiones-pendientes.md` y hoy están vacías. Son campos obligatorios
 * del formulario, y esa obligatoriedad se aplicará en la validación del servidor
 * en F1-04, cuando las listas tengan filas que elegir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinculaciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('persona_id')->constrained('personas');

            $tabla->string('cargo');
            $tabla->string('tipo_contrato');
            $tabla->date('fecha_ingreso');

            // Vacía en indefinido y en obra o labor (documento 1, 5.4).
            $tabla->date('fecha_fin')->nullable();

            // Dinero en decimal de precisión fija, nunca en coma flotante.
            $tabla->decimal('salario', 14, 2);
            $tabla->decimal('bonificacion', 14, 2)->default(0);

            $tabla->foreignId('eps_id')->nullable()->constrained('eps');
            $tabla->foreignId('fondo_pension_id')->nullable()->constrained('fondos_pension');
            $tabla->foreignId('arl_id')->nullable()->constrained('arls');
            $tabla->string('tipo_riesgo')->nullable();
            $tabla->foreignId('caja_compensacion_id')->nullable()->constrained('cajas_compensacion');
            $tabla->foreignId('banco_id')->nullable()->constrained('bancos');

            $tabla->string('estado')->default('activo');

            // El punto de partida del saldo que trae la carga inicial. Nulos en
            // toda vinculación registrada desde el formulario: ahí el saldo
            // arranca en cero desde la fecha de ingreso.
            $tabla->decimal('saldo_inicial_vacaciones', 8, 2)->nullable();
            $tabla->date('fecha_corte_saldo')->nullable();

            $tabla->timestamps();
            $tabla->softDeletes();

            // El listado de empleados filtra por estado y ordena por ingreso;
            // la ficha de una persona pide sus vinculaciones.
            $tabla->index(['tenant_id', 'estado', 'fecha_ingreso']);
            $tabla->index(['tenant_id', 'persona_id']);

            // El de la vista por defecto de contratos: los que vencen pronto.
            $tabla->index(['tenant_id', 'fecha_fin']);
        });

        PoliticaRls::activarEn('vinculaciones');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('vinculaciones');

        Schema::dropIfExists('vinculaciones');
    }
};
