# Estado del proyecto

**Última sesión:** 2026-08-18 · Tarea F0-07
**Verificación:** verde
**Siguiente tarea:** F0-08 · Auditoría

## Qué se hizo

- **Los cuatro flujos de acceso de la sección 5.1**, cada uno con su ruta, su controlador y su
  página Vue en `resources/js/Pages/Acceso/`: iniciar sesión, recuperar contraseña, restablecer
  contraseña y aceptar invitación. Las cuatro son rutas `guest`; `/` y `/salir` pasan a `auth`.
- **`App\Autenticacion\LimitadorDeIntentos`**: los dos primeros fallos salen gratis, el tercero cuesta
  un minuto y cada fallo siguiente duplica la espera hasta un tope de quince minutos. Cuenta por
  **correo e IP juntos**, no por uno solo, y vive en la caché.
- **Tabla `accesos`** y `App\Autenticacion\RegistroDeAccesos`: una línea por intento, exitoso, fallido
  o bloqueado, con correo tecleado, empresa cuando se conoce, IP y agente.
- **Tabla `invitaciones`** y `App\Autenticacion\Invitaciones`: token en claro solo en el correo, hash
  en la tabla, emitir invalida las anteriores del mismo usuario, y aceptar define la contraseña, marca
  el correo como verificado e inicia sesión.
- **Correos en español** (`resources/views/correos/`) para invitación y restablecimiento;
  `User::sendPasswordResetNotification()` reemplaza el de Laravel, que llegaba en inglés.
- **Componentes base nuevos**: `CampoTexto`, `Boton`, `Aviso`, `IndicadorFortaleza` y
  `Acceso/PanelDeAcceso`, más `Utilidades/fortalezaDeContrasena.js`. F0-09 los formaliza.
- **`tests/Feature/AutenticacionTest.php`** (18 casos) y **`tests/Unit/LimitadorDeIntentosTest.php`**
  (6 casos). `composer verificar` en verde: 88 pruebas de Pest, PHPStan sin errores, build y Vitest.

## Decisiones tomadas

- **Un solo mensaje de error al entrar y siempre el mismo.** Correo inexistente y contraseña
  equivocada responden idéntico; hay prueba que compara las dos respuestas. Decir «ese correo no
  existe» regala la lista de quién trabaja en la empresa.
- **`accesos` no sigue el patrón de tabla de negocio, a propósito.** Un intento fallido se escribe
  antes de saber de quién es: con `tenant_id` obligatorio y RLS activo, justo esas filas no se podrían
  escribir. La empresa se guarda nulable, cuando se conoce, y el aislamiento lo pondrá la pantalla que
  muestre el registro. Tampoco lleva borrado lógico: es registro de seguridad, como la auditoría.
- **`invitaciones` tampoco lleva `tenant_id` ni RLS.** El enlace se abre sin sesión iniciada, así que
  en esa petición no hay empresa en contexto y la política haría que la invitación no se encontrara
  nunca. La empresa se sabe por el usuario invitado.
- **El token de invitación se guarda con hash SHA-256**, igual que una contraseña: quien lea la tabla
  no puede fabricar el enlace de nadie.
- **Aceptar la invitación marca el correo como verificado.** Abrir un enlace que solo llegó a ese
  buzón es exactamente la prueba que la verificación de correo busca.
- **El enlace de recuperación expira en una hora**, como pide el documento: es el `expire => 60` que
  ya traía `config/auth.php`.

## Preguntas para Angela

*(ninguna)*

## Decisiones para David

- **Vigencia del enlace de invitación: siete días.** El documento fija la hora del enlace de
  recuperación pero no dice nada de la invitación, que es otra cosa: a la recuperación se responde en
  el momento, a una invitación puede que alguien entre el lunes siguiente. Está en
  `Invitaciones::DIAS_DE_VIGENCIA`, en un solo sitio. **Confírmalo o cámbialo.**
- **El rol de conexión de PostgreSQL.** Sigue abierta desde F0-04: `.env` se conecta como `postgres`,
  superusuario con `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace
  falta un rol corriente con `SELECT, INSERT, UPDATE, DELETE`. Conviene hacerlo también en local para
  no descubrirlo el día del despliegue.
- Sigue abierta de seis sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo sensible).
  Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()` copiando
  `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a mano
  —cambiar de empresa es siempre `paraTenant()`—, y que el rol de conexión no puede ser superusuario.

## Notas para la próxima sesión

- La siguiente es **F0-08 · Auditoría**: tabla `auditoria` y observador genérico con valores anterior
  y nuevo. Ojo: `accesos` **no** es la auditoría; son dos registros distintos y no hay que fundirlos.
  El observador tendrá que saltarse los modelos que no son de negocio (`Acceso`, `Invitacion`).
- **Nadie emite invitaciones todavía desde la interfaz.** `Invitaciones::emitir()` existe y está
  probado, pero la pantalla que lo llama es la de usuarios de Configuración (F1-12) y la creación de
  empresa del panel (F1B-02). Hasta entonces se emite desde tinker o desde una prueba.
- Los componentes `CampoTexto`, `Boton` y `Aviso` nacieron aquí por necesidad. **F0-09 no los crea de
  nuevo: los revisa contra `prototipos/sistema.html` y les quita cualquier color literal.**
- Cuando F0-10 monte las rutas reales de cada módulo, cada una lleva `->middleware('modulo:<nombre>')`
  y el menú se pinta con `usuario.modulos` de las props compartidas. Las rutas de juguete de
  `RolesYPermisosTest` desaparecen cuando existan las reales. El cierre de sesión ya es `POST /salir`.
- Sigue pendiente para F1-01: la migración de `personas` copia el stub y suma su `casoDeAislamiento()`.
