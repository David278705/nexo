<?php

namespace Database\Factories;

use App\Autenticacion\SegundoFactor;
use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // El más limitado de los tres: una prueba que necesite más permiso
            // tiene que pedirlo, no recibirlo por descuido.
            'rol' => Rol::Administrador->value,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * El proveedor. No pertenece a ninguna empresa.
     */
    public function owner(): static
    {
        return $this->state(fn () => [
            'rol' => Rol::Owner->value,
            'tenant_id' => null,
        ]);
    }

    /**
     * Con el segundo factor ya activo.
     *
     * El secreto se emite de verdad, no es un texto de mentira: así la prueba
     * puede leerlo y calcular el código de este momento, que es la única forma
     * de comprobar el flujo completo sin fingir el algoritmo.
     */
    public function conSegundoFactor(): static
    {
        return $this->state(fn () => [
            'segundo_factor_secreto' => app(SegundoFactor::class)->secretoNuevo(),
            'segundo_factor_confirmado_en' => now(),
        ]);
    }

    /**
     * La cuenta empresa: acceso total a los módulos de su empresa.
     */
    public function superadministrador(): static
    {
        return $this->state(fn () => ['rol' => Rol::Superadministrador->value]);
    }

    /**
     * Un administrador con los módulos indicados ya asignados.
     *
     * La asignación se hace después de crear el usuario porque `user_modulos`
     * necesita su identificador.
     */
    public function conModulos(Modulo ...$modulos): static
    {
        return $this->state(fn () => ['rol' => Rol::Administrador->value])
            ->afterCreating(function (User $usuario) use ($modulos) {
                foreach ($modulos as $modulo) {
                    $usuario->modulos()->create(['modulo' => $modulo->value]);
                }
            });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
