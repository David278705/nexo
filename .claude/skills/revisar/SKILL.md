---
name: revisar
description: Revisión de calidad del código construido hasta ahora, buscando fugas de aislamiento entre empresas, errores en el motor de cálculo, inconsistencias de interfaz y deuda acumulada. Úsalo en los puntos de control del backlog, al terminar una fase, o cuando el usuario pida revisar, auditar o verificar la calidad del proyecto.
---

# Revisión de calidad

No escribes funcionalidad nueva. Revisas lo construido, arreglas lo que encuentres y dejas constancia.

## 1. Aislamiento entre empresas

Es lo primero y lo más importante.

- Busca `where('tenant_id'` o `tenant_id =` escrito a mano en el código. Cada aparición es sospechosa:
  el filtro debe venir del trait. Investiga por qué está ahí.
- Verifica que **toda** tabla de negocio tenga el trait `PerteneceATenant` y su política RLS.
- Revisa los jobs: ¿restablecen el contexto de empresa al empezar? ¿lo limpian al terminar aunque fallen?
- Revisa los comandos programados: ¿recorren empresa por empresa o hay alguna consulta global?
- Cuenta los casos de `AislamientoTest.php` contra el número de tablas de negocio. Deben coincidir.

## 2. Motor de cálculo

- ¿Hay algún conteo de días fuera de `app/Dominio/Calculo/`? Si lo hay, muévelo.
- ¿Existe alguna columna que guarde el saldo de vacaciones como número mutable? No debe existir.
- Corre la batería del motor y confirma que están los diez casos del documento 2.
- Revisa `config/reglas_laborales.php`: ¿todo valor legal está ahí, con su norma citada?
- Busca números legales sueltos en el código: `1.25`, `2` meses, `30` días, `4` años.

## 3. Interfaz

- Busca colores literales (`#`, `rgb(`) en componentes Vue. Deben ser variables CSS.
- ¿Las pantallas de listado usan el mismo componente de tabla o alguna la reimplementó?
- ¿Todas tienen estado vacío con texto propio?
- ¿Las confirmaciones de borrado explican la consecuencia concreta o dicen «¿está seguro?»?
- ¿Las referencias temporales llevan la fecha entre paréntesis?

## 4. Seguridad y datos

- ¿Toda ruta verifica autorización en el servidor?
- ¿`codigo_diagnostico` tiene acceso restringido?
- ¿Hay borrado físico en algún sitio? Debe ser todo lógico.
- ¿La auditoría se escribe por observador o hay lugares donde se escribe a mano?
- ¿Algún secreto en el repositorio?

## 5. Deuda

- Bloques repetidos tres o más veces sin extraer.
- `TODO` y `FIXME` acumulados.
- Pruebas saltadas o comentadas.

## Cómo cerrar

Arregla lo que puedas dejar verde en esta misma sesión. Lo que no, conviértelo en tareas nuevas al
final de `BACKLOG.md` con prefijo `REV-`, descripción y motivo.

Escribe el informe en `bitacora/revision-AAAA-MM-DD.md`: qué revisaste, qué encontraste, qué
arreglaste y qué quedó pendiente. Haz commit.
