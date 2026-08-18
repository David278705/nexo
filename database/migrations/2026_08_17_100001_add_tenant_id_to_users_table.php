<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El middleware resuelve la empresa desde el usuario autenticado, así que
     * el usuario tiene que saber a cuál pertenece.
     *
     * Es nulable porque el rol `owner` del panel del proveedor no pertenece a
     * ninguna empresa; los roles llegan en F0-06.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('tenant_id');
        });
    }
};
