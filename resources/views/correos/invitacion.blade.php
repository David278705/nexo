@component('mail::message')
# Hola, {{ $nombre }}

@if ($empresa)
Te crearon una cuenta en {{ config('app.name') }} para gestionar el personal de {{ $empresa }}.
@else
Te crearon una cuenta en {{ config('app.name') }}.
@endif

Para entrar por primera vez, define tu contraseña:

@component('mail::button', ['url' => $enlace])
Definir mi contraseña
@endcomponent

El enlace vence en {{ $dias }} días. Si vence antes de que lo uses, pídele a quien te invitó que te
lo mande de nuevo.

Gracias,<br>
{{ config('app.name') }}
@endcomponent
