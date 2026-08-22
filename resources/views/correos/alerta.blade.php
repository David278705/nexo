{{--
    La plantilla base de las alertas (documento 2, 5.4).

    Encabezado con el nombre del sistema, cuerpo y pie con el nombre de la
    empresa y el enlace para entrar. Es una sola para las once alertas: lo que
    cambia entre ellas es el texto, y el texto lo escribe la regla de disparo
    con las tablas del capítulo 9. Aquí no se redacta nada ni se calcula
    ninguna fecha.
--}}
@component('mail::message')
@foreach ($parrafos as $parrafo)
{{ $parrafo }}

@endforeach
@component('mail::button', ['url' => $enlace])
Entrar a {{ config('app.name') }}
@endcomponent
{{ $empresa }}

@slot('subcopy')
Recibes este aviso porque tienes acceso al módulo de {{ $modulo }} en {{ $empresa }}.
@endslot
@endcomponent
