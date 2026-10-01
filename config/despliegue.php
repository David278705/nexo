<?php

/*
 * Lo que solo importa al desplegar: las piezas que leen `despliegue:preparar-base`
 * y `despliegue:verificar` al arrancar cada contenedor. La guía paso a paso está
 * en `docs/08-despliegue.md`.
 */

return [

    /*
     * La conexión del superusuario de PostgreSQL. En Railway es la
     * `DATABASE_URL` del servicio de base de datos.
     *
     * Se usa **para una sola cosa**: crear el rol con el que se conecta la
     * aplicación, que no puede ser superusuario porque PostgreSQL nunca le
     * aplica RLS a un superusuario (ver `App\Multiempresa\PoliticaRls`). La
     * aplicación misma nunca se conecta con esta URL.
     *
     * Es opcional: si el rol ya existe, puede quedar vacía. Y conviene borrarla
     * después del primer despliegue, porque cuanto menos dure un secreto de
     * superusuario dentro de un servicio expuesto a internet, mejor.
     */
    'url_administracion' => env('DB_URL_ADMIN'),

];
