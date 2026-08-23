<?php

namespace App\CargaInicial;

/**
 * Las ocho clases de dato que puede traer una celda de una plantilla.
 *
 * No es el tipo de la columna de la base de datos: es **cómo se lee la celda**.
 * `Lista` y `Catalogo` se parecen —las dos convierten un nombre en el valor que
 * guarda el sistema— y son distintas en algo que importa: la lista es un
 * enumerado del código y siempre cabe en un mensaje de error, y el catálogo es
 * una tabla que el proveedor administra y puede tener treinta y cuatro filas.
 *
 * `Dinero` y `Decimal` también se parecen y también son distintas: el dinero
 * sale de aquí como texto de precisión fija y nunca como coma flotante, que es
 * la convención del proyecto. El decimal es para lo que no es dinero —los días
 * de vacaciones con fracción— y ahí un número sí es un número.
 */
enum TipoDeCelda
{
    case Texto;

    case Fecha;

    case Entero;

    case Decimal;

    case Dinero;

    case SiNo;

    case Lista;

    case Catalogo;
}
