<?php

namespace App\Legal;

use App\Autorizacion\Rol;
use App\Models\AceptacionLegal;
use App\Models\Tenant;
use App\Models\User;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Si una empresa aceptó los documentos legales que el servicio exige, y la
 * constancia de cada aceptación.
 *
 * **La que acepta es la empresa**, a través de su superadministrador: es ella la
 * que contrata el servicio y la responsable del tratamiento de los datos de sus
 * empleados (Ley 1581 de 2012); el proveedor es el encargado. Por eso la
 * constancia guarda la empresa, pero también quién aceptó a su nombre, cuándo y
 * desde qué dirección.
 *
 * **Se acepta una versión, no un documento.** Si el contrato cambia y su versión
 * sube, la empresa queda pendiente otra vez, sin que haga falta marcar nada.
 *
 * Las consultas pasan por el aislamiento de siempre: quien usa esta clase ya está
 * dentro del contexto de la empresa —el middleware o la pantalla de
 * Configuración—, y `AceptacionLegal` filtra solo por su trait.
 */
final class AceptacionDeLaEmpresa
{
    private function __construct(
        private readonly Tenant $empresa,
        private readonly DocumentosLegales $documentos,
    ) {}

    public static function de(Tenant $empresa): self
    {
        return new self($empresa, app(DocumentosLegales::class));
    }

    /**
     * Los documentos que la empresa todavía no aceptó en su versión vigente.
     *
     * @return list<DocumentoLegal>
     */
    public function pendientes(): array
    {
        $aceptadas = $this->aceptaciones()
            ->map(fn (AceptacionLegal $a) => $a->documento.'@'.$a->version)
            ->all();

        return array_values(array_filter(
            $this->documentos->queExigenAceptacion(),
            fn (DocumentoLegal $d) => ! in_array($d->clave.'@'.$d->version, $aceptadas, true),
        ));
    }

    public function estaAlDia(): bool
    {
        return $this->pendientes() === [];
    }

    /**
     * Deja la constancia de todo lo pendiente, a nombre de la empresa.
     *
     * `firstOrCreate` sobre el índice único: dos clics seguidos en «Acepto» no
     * dejan dos constancias, y la segunda no pisa la hora de la primera.
     */
    public function aceptar(User $usuario, ?string $ip, ?string $agente): void
    {
        foreach ($this->pendientes() as $documento) {
            AceptacionLegal::query()->firstOrCreate(
                ['documento' => $documento->clave, 'version' => $documento->version],
                [
                    'user_id' => $usuario->getKey(),
                    // Quién aceptó, guardado en la constancia misma: el usuario
                    // se elimina si el cliente se va, la prueba no (`PROD-15`).
                    'aceptado_por_nombre' => $usuario->name,
                    'aceptado_por_correo' => $usuario->email,
                    'ip' => $ip,
                    'agente' => $agente === null ? null : mb_substr($agente, 0, 500),
                    'aceptado_en' => Carbon::now(),
                ],
            );
        }
    }

    /**
     * Quién puede aceptar a nombre de la empresa. Lo enseña la pantalla de
     * aceptación a los demás usuarios, que no pueden hacerlo.
     */
    public function superadministrador(): ?User
    {
        /** @var User|null $usuario */
        $usuario = $this->empresa->usuarios()->where('rol', Rol::Superadministrador)->first();

        return $usuario;
    }

    /**
     * La pestaña «Documentos legales» de Configuración: lo aceptado, con su
     * constancia, y lo que la empresa usa con su propia gente.
     *
     * @return array{legal: array{documentos: list<array<string, mixed>>, plantillas: list<array<string, string>>}}
     */
    public function paraLaPantalla(): array
    {
        $aceptaciones = $this->aceptaciones();

        $documentos = array_map(function (DocumentoLegal $documento) use ($aceptaciones): array {
            $aceptacion = $aceptaciones->first(
                fn (AceptacionLegal $a) => $a->documento === $documento->clave && $a->version === $documento->version,
            );

            return [
                'titulo' => $documento->titulo,
                'version' => $documento->version,
                'url' => $documento->url(),
                'aceptado' => $aceptacion !== null,
                'aceptadoEn' => $aceptacion === null ? null : Formato::fecha($aceptacion->aceptado_en),
                // El nombre guardado en la constancia, que es la prueba; el del
                // usuario solo para las anteriores a esa columna (`PROD-15`).
                'aceptadoPor' => $aceptacion === null
                    ? null
                    : ($aceptacion->aceptado_por_nombre ?? $aceptacion->usuario?->name),
            ];
        }, $this->documentos->queExigenAceptacion());

        $plantillas = array_map(
            fn (DocumentoLegal $documento) => ['titulo' => $documento->titulo, 'url' => $documento->url()],
            array_values(array_filter($this->documentos->todos(), fn (DocumentoLegal $d) => ! $d->exigeAceptacion)),
        );

        return ['legal' => ['documentos' => $documentos, 'plantillas' => $plantillas]];
    }

    /**
     * @return Collection<int, AceptacionLegal>
     */
    private function aceptaciones(): Collection
    {
        return AceptacionLegal::query()->with('usuario')->get();
    }
}
