# Estado del proyecto

**Última sesión:** 2026-11-13 · Prórrogas del término fijo según la propuesta de Angela (`PROD-33`). Antes: despliegue en Railway (`PROD-29` a `PROD-32`).
**Verificación:** verde (1483 pruebas de Pest, Vitest, PHPStan, Pint y build).
**Railway:** proyecto `nexo`, servicios web, worker y scheduler más Postgres, en
https://web-production-eac14903.up.railway.app. Queda solo la cuenta del proveedor (`owner@nexo.local`): la
empresa de pruebas se retira con `PROD-32` y Angela crea la suya desde el panel. El correo sale con el
remitente de prueba de la cuenta de Resend de Angela y solo a los correos de `MAIL_DESTINATARIOS_PERMITIDOS`;
el soporte es `soporte@nexo.invalid` hasta que haya uno real (paso 8 de la lista única).
**Simulación:** `docs/simulacion/` es el paquete para que Angela pruebe el sistema varias semanas con una
sesión de Claude. `generar_escenario.py` se validó contra el motor real: con día 1 el 12 y el 27 de noviembre
y el 2 de diciembre de 2026, las alertas del calendario coinciden una a una con las que genera el sistema.
**Dónde vamos:** el código está terminado. Lo que falta está en `docs/09-plan-de-salida-al-mercado.md`, y es
todo de David y Angela: trámites, contratos, cuentas, desplegar en Railway y probar con ojos propios.
**Siguiente sesión de código:** ninguna pendiente. Una sesión nueva solo hace falta si cambia una decisión,
llegan los textos del abogado (paso 12, que también puede hacer David) o aparece algo al desplegar.

**Regla para las próximas sesiones:** una decisión nueva, un hallazgo o una tarea nueva **entra a la lista
única en su lugar**. No se anota en este archivo ni en ningún otro como pendiente suelto.

## Qué se hizo el 2 y el 3 de noviembre

Revisión dirigida del código y plan de salida al mercado; la obra o labor con fecha estimada (`OL-01`); las
dependencias, los campos de dinero y el canal de soporte (`PROD-11` a `PROD-13`); los cinco documentos de
`docs/salida/` (`DOC-01`); y los cuatro cambios pedidos por David: la escalera de mora, lo que se conserva al
irse un cliente, la obra o labor a indefinido y la cuenta de prueba propia (`PROD-15` a `PROD-18`). El detalle
de cada uno está en su tarea del backlog. Lo que queda está en la lista única.

## Qué se hizo en la sesión anterior (2026-10-01)

David preguntó qué faltaba para salir a producción con solo desplegar, poner dominio y correo. Se
auditó el sistema contra la lista «G. Antes de vender la primera licencia» del documento 6 y se
encontraron cuatro bloques. Este es el primero, el de código, completo:

- **Una imagen y tres procesos** (`Dockerfile`, `docker/arrancar.sh`): web, worker de cola y
  scheduler. Sin los dos últimos, ninguna alerta salía.
- **RLS protege en producción**: `despliegue:preparar-base` crea el rol sin privilegios y
  `despliegue:verificar` no deja arrancar si la aplicación se conecta como superusuario.
- **El primer owner**: `equipo:invitar`, por invitación, sin contraseña en la terminal.
- **HTTPS detrás del proxy de Railway** y cabeceras de seguridad.
- **El disco de los certificados** configurable por variable.
- **El dinero sin `float`** en pagos y precios de plan.
- **Parches de seguridad** de Laravel, Flysystem y CommonMark.
- **El destino tras autenticarse en un solo sitio**: aceptar una invitación o restablecer la
  contraseña llevaban al owner a un 403. Era el mismo fallo que ya se había corregido en el acceso.
- **La guía**: `docs/08-despliegue.md`, paso a paso.

**El bloque 2, en su parte de código, también está completo** (`PROD-09` y `PROD-10`):

- **Documentos legales con versión** en `resources/legal/`, uno por archivo, y **nadie de una
  empresa trabaja hasta que su superadministrador los acepta**. Queda constancia de quién, qué
  versión, cuándo y desde qué IP. Hoy son borradores con un aviso, porque el texto no se inventa.
- **La salida del cliente (A.4)**: «Exportar toda la información» en la ficha del panel, y
  `empresa:eliminar {nit}` con cuatro candados. Es la única excepción a la regla 4, y está escrita
  así en el `CLAUDE.md`.

El detalle de cada pieza está en las tareas `PROD-*` del backlog y en los mensajes de sus commits.

## Decisiones tomadas

- **FrankenPHP en modo clásico, nunca en modo worker.** El modo worker mantiene la aplicación viva
  entre peticiones, y la empresa en curso de `ContextoTenant` podría pasar de una a la siguiente.
- **Un solo rol de base que también migra**, en lugar de un rol dueño y otro de solo datos. Es más
  simple, y `FORCE ROW LEVEL SECURITY` hace que el dueño tampoco se salte las políticas.
- **El contenedor corre como root**, igual que la imagen de FrankenPHP. Railway monta los volúmenes
  como root, y dejar de serlo pediría un paso más al arrancar. Queda anotado en el `Dockerfile`.
- **Sin una Content-Security-Policy de scripts**, solo `frame-ancestors`. Mal calibrada, una política
  de scripts deja la aplicación en blanco sin avisar.
- **El worker sin `--max-time`**: haría salir al proceso con código 0, y Railway, con su política por
  defecto, no lo reiniciaría.

## Preguntas para Angela y decisiones para David

Ya no se anotan aquí. Están en la lista única, `docs/09-plan-de-salida-al-mercado.md`: las de Angela y el
asesor laboral son el paso 2. Las decisiones de producto que estaban abiertas se cerraron el 2026-11-08 con
las recomendaciones (`PROD-21` a `PROD-27`).

## Notas para la próxima sesión

- **`despliegue:verificar` falla en local, y es lo correcto**: en local la base se conecta como
  `postgres`, que es superusuario. Sirve para ver de un vistazo qué le falta a una configuración.
- **Para correr un archivo de Pest suelto** hay que incluir el que define sus helpers: `usuarioDe()`
  vive en `RolesYPermisosTest.php`, `ownerEnElPanel()` en `CrearEmpresaTest.php`,
  `ownerVerificable()` en `PanelDelProveedorTest.php`. Y siempre `php -d memory_limit=1G`.
- **El entorno local no sobrevive al cierre de la sesión.** `php artisan serve --port=8014` hay que
  relanzarlo.
