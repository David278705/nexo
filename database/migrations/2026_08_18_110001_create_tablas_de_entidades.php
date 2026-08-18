<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las cinco listas de entidades del bloque de seguridad social y del bloque
 * laboral: EPS, fondos de pensión, ARL, cajas de compensación y bancos.
 *
 * Como países y ciudades, son catálogos globales: sin `tenant_id`, sin trait y
 * sin política de RLS. Las cinco tienen exactamente la misma forma, así que se
 * crean en un bucle: son la misma tabla con distinto nombre y separarlas en
 * cinco bloques idénticos solo invita a que una se quede atrás.
 *
 * `codigo` es el código oficial de la entidad —el de la Supersalud para las
 * EPS, el de la Superfinanciera para los bancos— y admite nulos porque no todas
 * las listas lo tienen. El nombre es único: es lo que ve y elige el usuario.
 *
 * **El contenido de estas cinco listas está pendiente de la decisión D.5 de
 * `docs/06-decisiones-pendientes.md`**, que es de Angela. Las tablas existen
 * desde ya porque su forma no depende de la respuesta; lo que falta son las
 * filas, y esas no se inventan. Su seeder está escrito y vacío, esperándolas.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tablas = [
        'eps',
        'fondos_pension',
        'arls',
        'cajas_compensacion',
        'bancos',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $nombreDeTabla) {
            Schema::create($nombreDeTabla, function (Blueprint $tabla) {
                $tabla->id();

                $tabla->string('codigo')->nullable();
                $tabla->string('nombre')->unique();
                $tabla->boolean('activo')->default(true);

                $tabla->timestamps();
                $tabla->softDeletes();

                $tabla->index('activo');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tablas) as $nombreDeTabla) {
            Schema::dropIfExists($nombreDeTabla);
        }
    }
};
