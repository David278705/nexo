# Estado del proyecto

**Última sesión:** 2026-10-01 · Preparación para producción, bloque 1 (código), tareas `PROD-01` a
`PROD-08` del backlog.
**Verificación:** verde. 1.351 de 1.351 pruebas de Pest, Pint y PHPStan sin errores, build y 415 de
Vitest. `composer audit` sin avisos.
**Siguiente tarea:** ninguna `[ ]` en el backlog. Lo que sigue son los bloques 2 a 4 de abajo, que
piden decisiones de David antes de escribir código.

## Qué se hizo

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

## Preguntas para Angela

- (sin cambios) B.2, el fondo de cesantías y la norma de `HD-04`.

## Decisiones para David

**Los bloques 2 a 4 de la auditoría, que siguen pendientes:**

- **Legal.** Sociedad y facturación electrónica ante la DIAN; contrato, acuerdo de tratamiento de
  datos y autorización para empleados revisados por abogado (B.4). En la app no hay términos ni
  política de tratamiento, y el superadministrador empieza a cargar datos de salud sin aceptar nada.
  Y A.4 promete entregarle al cliente que se va toda su información, pero no existe un «exportar
  todo».
- **Salir al mercado.** No hay landing (la recomendación es un sitio estático en el dominio raíz y la
  aplicación en `app.`), ni tabla de precios (A.1, `planes` vacía), ni pasarela de pagos (la lista G
  la exige antes de vender), ni canal de soporte visible para el cliente, ni marca (el favicon pesa
  0 bytes).
- **Operación.** Dominio y correo (`F3-06b`), el simulacro de restauración (`F6-07`, porque el plan
  de incidentes da por hecho un *Point-in-Time Recovery* de Railway que nadie ha verificado), y las
  verificaciones manuales en navegador que se arrastran desde `HD-04`.

**Al desplegar, tres cosas que solo se comprueban allá** (están en `docs/08-despliegue.md`, paso 8):

- **Que el registro de accesos guarde las IPs reales.** `trustProxies('*')` confía en un solo salto.
  Si Railway tuviera dos, todos los usuarios se verían con la IP del proxy y el límite de intentos
  los contaría como uno solo.
- **Que la imagen compile.** Aquí no había Docker. Se verificó todo lo que se podía verificar sin él
  —FrankenPHP real con el mismo comando, `optimize` en producción, que las imágenes base existan—,
  pero la compilación misma la hace Railway por primera vez.
- **Borrar `DB_URL_ADMIN`** después del primer despliegue.

**De antes, sin cambios:** si `/` debería desviar al owner a su panel en vez de darle 403;
`HD-05` (festivos históricos; se comprobó que el cálculo por ley Emiliani coincide fecha por fecha
con 2025, 2026 y 2027, y podría generar los años anteriores si alguien coteja una muestra);
`HD-12` y la plantilla de terminaciones que no existe; `stash@{0}`, que ya no sirve.

**Un riesgo que no se tocó:** el campo «Monto recibido» es un número del navegador. Si alguien
escribe «380.000», el navegador lo convierte en 380 antes de enviarlo, y el servidor no lo puede
distinguir. La confirmación del pago enseña el monto, pero un campo de dinero con formato colombiano
lo evitaría del todo.

## Notas para la próxima sesión

- **`despliegue:verificar` falla en local, y es lo correcto**: en local la base se conecta como
  `postgres`, que es superusuario. Sirve para ver de un vistazo qué le falta a una configuración.
- **Para correr un archivo de Pest suelto** hay que incluir el que define sus helpers: `usuarioDe()`
  vive en `RolesYPermisosTest.php`, `ownerEnElPanel()` en `CrearEmpresaTest.php`,
  `ownerVerificable()` en `PanelDelProveedorTest.php`. Y siempre `php -d memory_limit=1G`.
- **El entorno local no sobrevive al cierre de la sesión.** `php artisan serve --port=8014` hay que
  relanzarlo.
