<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La tabla de empresas cliente. Es la única tabla del lado del cliente que
     * no lleva `tenant_id`: es la raíz de la que cuelga todo lo demás.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $tabla) {
            $tabla->id();

            // Datos de la empresa (doc. 04, 4.2 paso 1). La ciudad queda como
            // texto hasta que F0-11 traiga el catálogo de ciudades de Colombia.
            $tabla->string('nombre');
            $tabla->string('nit')->unique();
            $tabla->string('telefono')->nullable();
            $tabla->string('ciudad')->nullable();

            // Estado comercial de la cuenta (doc. 04, 4.1): activa, suspendida
            // o en periodo de prueba. Enumerado como texto legible, no como
            // número, para que una exportación se entienda sin diccionario.
            $tabla->string('estado');

            // Los tres parámetros de cálculo (doc. 01, cap. 4). Sin valor por
            // omisión a propósito: cuál es el correcto es una decisión del
            // cliente que se toma en el onboarding, no algo que se pueda dar
            // por supuesto en una migración.
            $tabla->boolean('sabado_laboral');
            $tabla->boolean('suspensiones_afectan_vacaciones');
            $tabla->boolean('perfil_sociodemografico_habilitado');

            // Campos de salud (doc. 02, 3.5). Se guardan en vez de calcularse
            // porque el listado de empresas del panel los consulta en cada
            // carga. Los refresca el proceso diario de alertas.
            $tabla->timestamp('ultimo_acceso_en')->nullable();
            $tabla->unsignedInteger('empleados_cargados')->default(0);
            $tabla->timestamp('ultima_novedad_en')->nullable();

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
