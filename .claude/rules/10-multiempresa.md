---
paths:
  - "app/**/*.php"
  - "database/migrations/**/*.php"
  - "routes/**/*.php"
---

# Aislamiento entre empresas

Es el fallo que mata un SaaS: que una empresa vea la nómina de otra. Hay dos capas y ninguna es opcional.

## Capa 1 — el trait, no el código

Todo modelo de negocio usa el trait `PerteneceATenant`, que:

- Aplica un global scope con `where tenant_id = tenant_actual()` a toda consulta.
- Rellena `tenant_id` automáticamente al crear.

**Nunca escribas `where('tenant_id', ...)` a mano.** Si aparece en el código, es señal de que el
modelo no tiene el trait o de que alguien lo desactivó.

Tablas globales que **no** llevan `tenant_id` ni el trait: `paises`, `departamentos`, `ciudades`,
`eps`, `fondos_pension`, `arls`, `cajas_compensacion`, `bancos`, `festivos`, `planes`.

## Capa 2 — Row Level Security en PostgreSQL

Cada migración que crea una tabla de negocio activa RLS y su política en la misma migración.
El identificador de empresa viaja en una variable de sesión que fija el middleware al inicio de
cada petición.

RLS existe porque el global scope se puede saltar: SQL directo, query builder, comandos de consola.
Es la última línea de defensa y se escribe desde el primer día, no después.

## Reglas de código

- El tenant se resuelve **una sola vez por petición**, desde el usuario autenticado, en un middleware.
  Jamás desde un parámetro de la URL, del formulario o de una cabecera.
- Los **jobs en cola** serializan el `tenant_id` y lo restablecen al empezar, con limpieza garantizada
  al terminar aunque el job falle. Es el error clásico: un proceso en segundo plano corre sin contexto.
- Los **comandos programados** que recorren empresas lo hacen empresa por empresa, fijando el contexto
  en cada iteración. Nunca con una consulta global.
- El rol `owner` es el único que atraviesa empresas, y solo desde el panel del proveedor. Su acceso al
  entorno de un cliente queda en la auditoría **de ese cliente**, visible para él.

## Prueba obligatoria

Toda tabla de negocio nueva suma un caso a `tests/Feature/AislamientoTest.php`: crear el registro en
la empresa A, autenticarse en la empresa B, e intentar leerlo por todos los caminos posibles
(modelo, ruta, exportación, búsqueda). Si alguno lo devuelve, la prueba falla.

No des una tarea por terminada si agregaste una tabla de negocio y no agregaste su caso de aislamiento.
