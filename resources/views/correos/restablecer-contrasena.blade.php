@component('mail::message')
# Restablece tu contraseña

Recibimos una solicitud para cambiar la contraseña de tu cuenta.

@component('mail::button', ['url' => $enlace])
Definir una contraseña nueva
@endcomponent

El enlace vence en {{ $minutos }} minutos. Si no fuiste tú quien lo pidió, no tienes que hacer nada:
tu contraseña sigue siendo la misma.

Gracias,<br>
{{ config('app.name') }}
@endcomponent
